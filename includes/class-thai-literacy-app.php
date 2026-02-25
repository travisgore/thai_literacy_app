<?php

if (!defined('ABSPATH')) {
    exit;
}

class Thai_Literacy_App {
    const OPTION_KEY = 'tla_settings';
    const DEFAULT_CONTENT_KEY = 'default_content';
    const POST_TYPE = 'tla_literacy_game';

    public function run() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_game_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_tla_generate_tts_audio', [$this, 'ajax_generate_tts_audio']);
        add_action('wp_ajax_tla_delete_audio_attachment', [$this, 'ajax_delete_audio_attachment']);

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_tla_download_csv_template', [$this, 'download_csv_template']);
        add_action('admin_post_tla_upload_csv', [$this, 'handle_csv_upload']);
        add_action('admin_post_tla_download_post_csv_template', [$this, 'download_post_csv_template']);
        add_action('admin_post_tla_download_post_csv', [$this, 'download_post_csv']);
        add_action('admin_post_tla_upload_post_csv', [$this, 'handle_post_csv_upload']);
        add_action('admin_notices', [$this, 'render_post_csv_notice']);

        add_shortcode('thai_literacy_app', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public static function activate() {
        self::register_post_type_static();
        flush_rewrite_rules();

        global $wpdb;
        $table_name = $wpdb->prefix . 'tla_attempts';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            item_id VARCHAR(128) NOT NULL,
            mode VARCHAR(64) NOT NULL,
            correct TINYINT(1) NOT NULL DEFAULT 0,
            error_tags TEXT NULL,
            latency_ms INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_item (user_id, item_id)
        ) {$charset_collate};";

        dbDelta($sql);

        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, self::default_settings());
        }

        self::seed_default_posts();
    }

    public static function register_post_type_static() {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Thai Literacy Games', 'thai-literacy-app'),
                'singular_name' => __('Thai Literacy Game', 'thai-literacy-app'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'thai-literacy-game'],
        ]);
    }

    public function register_post_type() {
        self::register_post_type_static();
    }

    public static function default_settings() {
        return [
            'app_title' => 'Learn to Read Thai',
            'show_ipa' => 1,
            'default_mode' => 'syllable_decoder',
            'lesson_flow' => [
                'Get comfortable with Thai letters',
                'Learn consonant sounds',
                'Learn consonant classes (high, mid, low)',
                'Learn common vowel patterns',
                'Build simple Thai words',
                'Practice ending consonants',
                'Learn the tone marks',
                'Practice tricky spelling patterns',
                'Read common everyday words',
                'Read short phrases',
                'Read real-world Thai text',
            ],
            self::DEFAULT_CONTENT_KEY => [
                'vowels' => [
                    ['id' => 'v_aa', 'label' => 'Sara Aa', 'pattern' => '◌า', 'with_aw_ang' => 'อา', 'sound' => 'aa', 'hint' => 'Long aa sound.'],
                    ['id' => 'v_am', 'label' => 'Sara Am', 'pattern' => '◌ำ', 'with_aw_ang' => 'อำ', 'sound' => 'am', 'hint' => 'Short vowel plus final m sound.'],
                    ['id' => 'v_ai', 'label' => 'Sara Ai Mai Malai', 'pattern' => 'ไ◌', 'with_aw_ang' => 'ไอ', 'sound' => 'ai', 'hint' => 'Diphthong ai sound.'],
                ],
                'consonants' => [
                    ['id' => 'c_k_mid', 'glyph' => 'ก', 'name' => 'Ko Kai', 'class' => 'mid', 'sound' => 'k', 'hint' => 'Middle-class consonant with k sound.'],
                    ['id' => 'c_n_low', 'glyph' => 'น', 'name' => 'No Nu', 'class' => 'low', 'sound' => 'n', 'hint' => 'Low-class consonant with n sound.'],
                    ['id' => 'c_m_low', 'glyph' => 'ม', 'name' => 'Mo Ma', 'class' => 'low', 'sound' => 'm', 'hint' => 'Low-class consonant with m sound.'],
                ],
                'tone_markers' => [
                    ['id' => 'none', 'label' => 'No tone mark', 'glyph' => '', 'default_tone' => 'mid', 'hint' => 'Use when there is no tone marker.'],
                    ['id' => 'mai_ek', 'label' => 'Mai Ek', 'glyph' => '่', 'default_tone' => 'low', 'hint' => 'Common tone marker for low tone patterns.'],
                    ['id' => 'mai_tho', 'label' => 'Mai Tho', 'glyph' => '้', 'default_tone' => 'falling', 'hint' => 'Common tone marker for falling tone patterns.'],
                ],
                'combinations' => [
                    ['id' => 'cmb_c_k_mid_v_aa_none', 'consonant_id' => 'c_k_mid', 'vowel_id' => 'v_aa', 'tone_marker_id' => 'none', 'text' => 'กา', 'tone' => 'mid', 'pronunciation' => 'gaa', 'description' => 'crow', 'audio_url' => ''],
                    ['id' => 'cmb_c_n_low_v_am_mai_tho', 'consonant_id' => 'c_n_low', 'vowel_id' => 'v_am', 'tone_marker_id' => 'mai_tho', 'text' => 'น้ำ', 'tone' => 'high', 'pronunciation' => 'nam', 'description' => 'water', 'audio_url' => ''],
                    ['id' => 'cmb_c_m_low_v_aa_mai_ek', 'consonant_id' => 'c_m_low', 'vowel_id' => 'v_aa', 'tone_marker_id' => 'mai_ek', 'text' => 'ม่า', 'tone' => 'falling', 'pronunciation' => 'maa', 'description' => 'grandmother (contextual)', 'audio_url' => ''],
                ],
                'games' => [
                    ['id' => 'syllable_decoder', 'name' => 'Read a Word', 'description' => 'See a Thai word, then choose the correct tone.'],
                    ['id' => 'grapheme_snap', 'name' => 'Symbol Match', 'description' => 'Learn each Thai symbol type, then classify it quickly.'],
                    ['id' => 'tone_calculator', 'name' => 'Tone Helper', 'description' => 'Use consonant class + tone mark + word type to find the tone.'],
                    ['id' => 'vowel_assembler', 'name' => 'Vowel Match', 'description' => 'Match the target sound with the correct Thai vowel pattern.'],
                ],
            ],
        ];
    }

    public static function seed_default_posts() {
        $existing = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids',
        ]);

        if (!empty($existing)) {
            return;
        }

        $settings = self::default_settings();
        $baseContent = $settings[self::DEFAULT_CONTENT_KEY];
        $modes = ['syllable_decoder', 'tone_calculator', 'grapheme_snap', 'vowel_assembler'];

        foreach ($modes as $mode) {
            $post_id = wp_insert_post([
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => ucwords(str_replace('_', ' ', $mode)) . ' Starter',
                'post_content' => "Starter content preloaded for {$mode}.",
            ]);

            if (!is_wp_error($post_id) && $post_id) {
                update_post_meta($post_id, '_tla_game_mode', $mode);
                update_post_meta($post_id, '_tla_app_title', $settings['app_title']);
                update_post_meta($post_id, '_tla_show_ipa', $settings['show_ipa']);
                update_post_meta($post_id, '_tla_default_mode', $mode);
                update_post_meta($post_id, '_tla_lesson_flow', $settings['lesson_flow']);
                update_post_meta($post_id, '_tla_content_json', wp_json_encode($baseContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
    }

    public function register_meta_boxes() {
        add_meta_box(
            'tla_game_config',
            __('Thai Literacy Game Setup', 'thai-literacy-app'),
            [$this, 'render_game_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_game_meta_box($post) {
        wp_nonce_field('tla_save_game_meta', 'tla_game_meta_nonce');

        $game_mode = get_post_meta($post->ID, '_tla_game_mode', true) ?: 'syllable_decoder';
        $app_title = get_post_meta($post->ID, '_tla_app_title', true) ?: 'Learn to Read Thai';
        $show_ipa = (int) get_post_meta($post->ID, '_tla_show_ipa', true);
        $default_mode = get_post_meta($post->ID, '_tla_default_mode', true) ?: 'syllable_decoder';
        $lesson_flow = get_post_meta($post->ID, '_tla_lesson_flow', true);
        $content_json = get_post_meta($post->ID, '_tla_content_json', true);

        if (empty($lesson_flow)) {
            $lesson_flow = self::default_settings()['lesson_flow'];
        }

        $content_data = json_decode($content_json, true);
        if (!is_array($content_data)) {
            $content_data = self::default_settings()[self::DEFAULT_CONTENT_KEY];
        }
        $content_json = wp_json_encode($content_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($content_json)) {
            $content_json = '{}';
        }

        $shortcode_auto = '[thai_literacy_app]';
        $shortcode_specific = sprintf('[thai_literacy_app post_id="%d"]', (int) $post->ID);
        $shortcode_input_id = 'tla_shortcode_' . (int) $post->ID;
        $shortcode_feedback_id = 'tla_shortcode_feedback_' . (int) $post->ID;
        $template_url = wp_nonce_url(
            admin_url('admin-post.php?action=tla_download_post_csv_template&post_id=' . (int) $post->ID),
            'tla_post_csv_template_' . (int) $post->ID
        );
        $download_url = wp_nonce_url(
            admin_url('admin-post.php?action=tla_download_post_csv&post_id=' . (int) $post->ID),
            'tla_post_csv_download_' . (int) $post->ID
        );
        ?>
        <p>
            <label for="tla_game_mode"><strong><?php esc_html_e('Primary Activity', 'thai-literacy-app'); ?></strong></label><br />
            <select id="tla_game_mode" name="tla_game_mode">
                <option value="syllable_decoder" <?php selected($game_mode, 'syllable_decoder'); ?>>Read a word</option>
                <option value="tone_calculator" <?php selected($game_mode, 'tone_calculator'); ?>>Tone helper</option>
                <option value="grapheme_snap" <?php selected($game_mode, 'grapheme_snap'); ?>>Symbol match</option>
                <option value="vowel_assembler" <?php selected($game_mode, 'vowel_assembler'); ?>>Vowel match</option>
            </select>
        </p>
        <p>
            <label for="tla_app_title"><strong><?php esc_html_e('App Title', 'thai-literacy-app'); ?></strong></label><br />
            <input id="tla_app_title" type="text" name="tla_app_title" value="<?php echo esc_attr($app_title); ?>" class="regular-text" />
        </p>
        <p>
            <label for="tla_default_mode"><strong><?php esc_html_e('Starting Activity', 'thai-literacy-app'); ?></strong></label><br />
            <select id="tla_default_mode" name="tla_default_mode">
                <option value="syllable_decoder" <?php selected($default_mode, 'syllable_decoder'); ?>>Read a word</option>
                <option value="tone_calculator" <?php selected($default_mode, 'tone_calculator'); ?>>Tone helper</option>
                <option value="grapheme_snap" <?php selected($default_mode, 'grapheme_snap'); ?>>Symbol match</option>
                <option value="vowel_assembler" <?php selected($default_mode, 'vowel_assembler'); ?>>Vowel match</option>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="tla_show_ipa" value="1" <?php checked($show_ipa, 1); ?> />
                <?php esc_html_e('Show pronunciation hints', 'thai-literacy-app'); ?>
            </label>
        </p>
        <section class="tla-admin-block">
            <h3><?php esc_html_e('Display This Game', 'thai-literacy-app'); ?></h3>
            <p><?php esc_html_e('Place this shortcode in any page, post, or template to render this exact game configuration.', 'thai-literacy-app'); ?></p>
            <div class="tla-shortcode-row">
                <input
                    id="<?php echo esc_attr($shortcode_input_id); ?>"
                    type="text"
                    readonly
                    class="regular-text code"
                    value="<?php echo esc_attr($shortcode_specific); ?>"
                />
                <button
                    type="button"
                    class="button button-secondary"
                    data-action="copy-shortcode"
                    data-copy-target="<?php echo esc_attr($shortcode_input_id); ?>"
                    data-feedback-target="<?php echo esc_attr($shortcode_feedback_id); ?>"
                >
                    <?php esc_html_e('Copy', 'thai-literacy-app'); ?>
                </button>
            </div>
            <p id="<?php echo esc_attr($shortcode_feedback_id); ?>" class="description tla-shortcode-feedback" aria-live="polite"></p>
            <p class="description">
                <?php esc_html_e('Use [thai_literacy_app] only when embedding directly on a Thai Literacy Game single post.', 'thai-literacy-app'); ?>
                <code><?php echo esc_html($shortcode_auto); ?></code>
            </p>
        </section>
        <section class="tla-admin-block">
            <h3><?php esc_html_e('CSV Tools For This Post', 'thai-literacy-app'); ?></h3>
            <p><?php esc_html_e('Import/export a single CSV row for this post only.', 'thai-literacy-app'); ?></p>
            <div class="tla-csv-actions">
                <a href="<?php echo esc_url($template_url); ?>" class="button button-secondary"><?php esc_html_e('Download CSV Template', 'thai-literacy-app'); ?></a>
                <a href="<?php echo esc_url($download_url); ?>" class="button button-secondary"><?php esc_html_e('Download This Post CSV', 'thai-literacy-app'); ?></a>
            </div>
            <div class="tla-csv-upload-form">
                <?php wp_nonce_field('tla_post_csv_upload_' . (int) $post->ID, 'tla_post_csv_upload_nonce'); ?>
                <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post->ID); ?>" />
                <input type="file" name="tla_csv_file" accept=".csv,text/csv" />
                <button
                    type="submit"
                    formaction="<?php echo esc_url(admin_url('admin-post.php?action=tla_upload_post_csv')); ?>"
                    formmethod="post"
                    formenctype="multipart/form-data"
                    formnovalidate
                    class="button button-primary"
                >
                    <?php esc_html_e('Upload CSV To This Post', 'thai-literacy-app'); ?>
                </button>
            </div>
            <p class="description"><?php esc_html_e('CSV columns: post_title, post_status, game_mode, app_title, show_ipa, default_mode, lesson_flow_json, content_json', 'thai-literacy-app'); ?></p>
        </section>
        <p>
            <strong><?php esc_html_e('Content Builder', 'thai-literacy-app'); ?></strong>
            <span class="description"><?php esc_html_e('Add and edit training content using a structured editor. JSON is maintained automatically.', 'thai-literacy-app'); ?></span>
        </p>
        <div id="tla_content_editor" class="tla-content-editor" aria-live="polite">
            <p class="description"><?php esc_html_e('Loading content editor…', 'thai-literacy-app'); ?></p>
        </div>
        <textarea id="tla_content_json" name="tla_content_json" class="tla-content-json-field"><?php echo esc_textarea($content_json); ?></textarea>
        <noscript>
            <p>
                <label for="tla_content_json_noscript"><strong><?php esc_html_e('Content JSON (fallback)', 'thai-literacy-app'); ?></strong></label><br />
                <textarea id="tla_content_json_noscript" name="tla_content_json" rows="20" class="code" style="width:100%;"><?php echo esc_textarea($content_json); ?></textarea>
            </p>
        </noscript>
        <p class="description">
            <?php esc_html_e('Tip: save this post after major changes so CSV export and shortcode output stay in sync.', 'thai-literacy-app'); ?>
        </p>
        <?php
    }

    public function save_game_meta($post_id) {
        if (!isset($_POST['tla_game_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tla_game_meta_nonce'])), 'tla_save_game_meta')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $game_mode = sanitize_key($_POST['tla_game_mode'] ?? 'syllable_decoder');
        $app_title = sanitize_text_field($_POST['tla_app_title'] ?? 'Learn to Read Thai');
        $show_ipa = !empty($_POST['tla_show_ipa']) ? 1 : 0;
        $default_mode = sanitize_key($_POST['tla_default_mode'] ?? 'syllable_decoder');

        $lesson_flow = get_post_meta($post_id, '_tla_lesson_flow', true);
        if (!is_array($lesson_flow)) {
            $lesson_flow = self::default_settings()['lesson_flow'];
        }
        if (isset($_POST['tla_lesson_flow'])) {
            $lesson_raw = sanitize_textarea_field($_POST['tla_lesson_flow']);
            $lesson_flow = array_values(array_filter(array_map('trim', explode("\n", $lesson_raw))));
        }

        $content_json_raw = wp_unslash($_POST['tla_content_json'] ?? '');
        $decoded = json_decode($content_json_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $decoded = self::default_settings()[self::DEFAULT_CONTENT_KEY];
        }

        update_post_meta($post_id, '_tla_game_mode', $game_mode);
        update_post_meta($post_id, '_tla_app_title', $app_title);
        update_post_meta($post_id, '_tla_show_ipa', $show_ipa);
        update_post_meta($post_id, '_tla_default_mode', $default_mode);
        update_post_meta($post_id, '_tla_lesson_flow', $lesson_flow);
        update_post_meta($post_id, '_tla_content_json', wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function register_assets() {
        wp_register_style('tla-app-style', TLA_PLUGIN_URL . 'assets/css/app.css', [], TLA_PLUGIN_VERSION);
        wp_register_script('tla-app-script', TLA_PLUGIN_URL . 'assets/js/app.js', [], TLA_PLUGIN_VERSION, true);
    }

    public function enqueue_admin_assets($hook_suffix) {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || self::POST_TYPE !== $screen->post_type) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('tla-admin-editor-style', TLA_PLUGIN_URL . 'assets/css/admin-editor.css', [], TLA_PLUGIN_VERSION);
        wp_enqueue_script('tla-admin-editor-script', TLA_PLUGIN_URL . 'assets/js/admin-content-editor.js', [], TLA_PLUGIN_VERSION, true);
        wp_localize_script('tla-admin-editor-script', 'TLA_ADMIN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tla_admin_audio'),
            'postId' => absint($_GET['post'] ?? 0),
        ]);
    }

    private function tts_audio_field_map() {
        return [
            'audio_url' => [
                'attachment_key' => 'audio_attachment_id',
            ],
            'vowel_audio_url' => [
                'attachment_key' => 'vowel_audio_attachment_id',
            ],
        ];
    }

    private function get_tts_endpoint_url() {
        if (defined('LLP_GOOGLE_TTS_ENDPOINT')) {
            $configured = trim((string) LLP_GOOGLE_TTS_ENDPOINT);
            if (filter_var($configured, FILTER_VALIDATE_URL)) {
                return $configured;
            }
        }
        return 'https://texttospeech.googleapis.com/v1/text:synthesize';
    }

    private function get_tts_service_account_path() {
        $candidates = [];

        if (defined('LLP_GOOGLE_TTS_SERVICE_ACCOUNT_JSON')) {
            $candidates[] = (string) LLP_GOOGLE_TTS_SERVICE_ACCOUNT_JSON;
        }

        if (defined('LLP_GOOGLE_TTS_ENDPOINT')) {
            $candidates[] = (string) LLP_GOOGLE_TTS_ENDPOINT;
        }

        foreach ($candidates as $candidate) {
            $trimmed = trim($candidate);
            if ('' === $trimmed) {
                continue;
            }
            if (!filter_var($trimmed, FILTER_VALIDATE_URL) && is_readable($trimmed)) {
                return $trimmed;
            }
        }

        return '';
    }

    private function get_tts_service_account_data() {
        $path = $this->get_tts_service_account_path();
        if ('' === $path) {
            return new WP_Error('tts_config_missing', __('Google TTS service account file path is missing.', 'thai-literacy-app'));
        }

        $raw = file_get_contents($path);
        if (false === $raw) {
            return new WP_Error('tts_config_unreadable', __('Google TTS service account file could not be read.', 'thai-literacy-app'));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new WP_Error('tts_config_invalid', __('Google TTS service account JSON is invalid.', 'thai-literacy-app'));
        }

        $required = ['client_email', 'private_key'];
        foreach ($required as $key) {
            if (empty($decoded[$key]) || !is_string($decoded[$key])) {
                return new WP_Error('tts_config_invalid', __('Google TTS service account JSON is missing required keys.', 'thai-literacy-app'));
            }
        }

        if (empty($decoded['token_uri']) || !is_string($decoded['token_uri'])) {
            $decoded['token_uri'] = 'https://oauth2.googleapis.com/token';
        }

        return $decoded;
    }

    private function base64url_encode($input) {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function get_google_access_token($service_account) {
        $transient_key = 'tla_tts_token_' . md5((string) $service_account['client_email']);
        $cached = get_transient($transient_key);
        if (is_array($cached) && !empty($cached['access_token'])) {
            return $cached['access_token'];
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $service_account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => $service_account['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $unsigned_jwt = $this->base64url_encode(wp_json_encode($header)) . '.' . $this->base64url_encode(wp_json_encode($claims));
        $signature = '';
        $signed = openssl_sign($unsigned_jwt, $signature, $service_account['private_key'], OPENSSL_ALGO_SHA256);
        if (!$signed) {
            return new WP_Error('tts_auth_sign_failed', __('Unable to sign Google TTS auth request.', 'thai-literacy-app'));
        }

        $jwt = $unsigned_jwt . '.' . $this->base64url_encode($signature);
        $response = wp_remote_post($service_account['token_uri'], [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('tts_auth_request_failed', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($body) || empty($body['access_token'])) {
            return new WP_Error('tts_auth_invalid_response', __('Google auth token request failed.', 'thai-literacy-app'));
        }

        $expires_in = isset($body['expires_in']) ? absint($body['expires_in']) : 3600;
        set_transient($transient_key, ['access_token' => $body['access_token']], max(60, $expires_in - 120));

        return $body['access_token'];
    }

    private function create_audio_attachment($post_id, $audio_binary, $base_name = 'tts-audio') {
        $slug = sanitize_file_name($base_name);
        if ('' === $slug) {
            $slug = 'tts-audio';
        }

        $filename = $slug . '-' . gmdate('Ymd-His') . '-' . wp_generate_password(6, false, false) . '.mp3';
        $uploaded = wp_upload_bits($filename, null, $audio_binary);
        if (!empty($uploaded['error'])) {
            return new WP_Error('tts_upload_failed', $uploaded['error']);
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => 'audio/mpeg',
            'post_title' => preg_replace('/\.mp3$/', '', $filename),
            'post_status' => 'inherit',
        ], $uploaded['file'], $post_id);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return new WP_Error('tts_attachment_failed', __('Unable to create media attachment for generated audio.', 'thai-literacy-app'));
        }

        $url = wp_get_attachment_url($attachment_id);
        if (!$url) {
            return new WP_Error('tts_attachment_failed', __('Unable to retrieve media URL for generated audio.', 'thai-literacy-app'));
        }

        return [
            'attachment_id' => (int) $attachment_id,
            'url' => (string) $url,
        ];
    }

    private function random_tts_gender() {
        return wp_rand(0, 1) ? 'MALE' : 'FEMALE';
    }

    private function get_tts_voices_endpoint_url() {
        $synthesize_endpoint = $this->get_tts_endpoint_url();
        if (false !== strpos($synthesize_endpoint, 'text:synthesize')) {
            return str_replace('text:synthesize', 'voices', $synthesize_endpoint);
        }
        return 'https://texttospeech.googleapis.com/v1/voices';
    }

    private function get_preferred_chirp_voice_name($access_token, $gender = 'NEUTRAL') {
        $gender_key = strtolower((string) $gender);
        $cache_key = 'tla_tts_chirp_voice_th_' . $gender_key;
        $cached = get_transient($cache_key);
        if (is_string($cached) && '' !== $cached) {
            return $cached;
        }

        $response = wp_remote_get(add_query_arg(['languageCode' => 'th-TH'], $this->get_tts_voices_endpoint_url()), [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($body) || !isset($body['voices']) || !is_array($body['voices'])) {
            return '';
        }

        $target_gender = strtoupper((string) $gender);
        $fallback_voice = '';
        foreach ($body['voices'] as $voice) {
            if (!is_array($voice)) {
                continue;
            }

            $name = isset($voice['name']) ? (string) $voice['name'] : '';
            if ('' === $name || false === stripos($name, 'chirp')) {
                continue;
            }

            $languages = isset($voice['languageCodes']) && is_array($voice['languageCodes']) ? $voice['languageCodes'] : [];
            if (!in_array('th-TH', $languages, true)) {
                continue;
            }

            if ('' === $fallback_voice) {
                $fallback_voice = $name;
            }

            $voice_gender = strtoupper((string) ($voice['ssmlGender'] ?? ''));
            if ($target_gender && $voice_gender === $target_gender) {
                set_transient($cache_key, $name, 6 * HOUR_IN_SECONDS);
                return $name;
            }
        }

        if ('' !== $fallback_voice) {
            set_transient($cache_key, $fallback_voice, 6 * HOUR_IN_SECONDS);
            return $fallback_voice;
        }

        return '';
    }

    public function ajax_generate_tts_audio() {
        check_ajax_referer('tla_admin_audio', 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        if ($post_id > 0) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error(['message' => __('You are not allowed to edit this post.', 'thai-literacy-app')], 403);
            }
        } elseif (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('You are not allowed to upload media.', 'thai-literacy-app')], 403);
        }

        $field_key = sanitize_key($_POST['field_key'] ?? '');
        $field_map = $this->tts_audio_field_map();
        if (!isset($field_map[$field_key])) {
            wp_send_json_error(['message' => __('Invalid audio field.', 'thai-literacy-app')], 400);
        }

        $text = trim((string) wp_unslash($_POST['text'] ?? ''));
        if ('' === $text) {
            wp_send_json_error(['message' => __('Cannot generate audio because the source text is empty.', 'thai-literacy-app')], 400);
        }

        $existing_attachment_id = absint($_POST['existing_attachment_id'] ?? 0);
        if ($existing_attachment_id && current_user_can('delete_post', $existing_attachment_id)) {
            wp_delete_attachment($existing_attachment_id, true);
        }

        $service_account = $this->get_tts_service_account_data();
        if (is_wp_error($service_account)) {
            wp_send_json_error(['message' => $service_account->get_error_message()], 500);
        }

        $access_token = $this->get_google_access_token($service_account);
        if (is_wp_error($access_token)) {
            wp_send_json_error(['message' => $access_token->get_error_message()], 500);
        }

        $gender = $this->random_tts_gender();
        $preferred_voice = $this->get_preferred_chirp_voice_name($access_token, $gender);
        $endpoint = $this->get_tts_endpoint_url();
        $voice_payload = [
            'languageCode' => 'th-TH',
            'ssmlGender' => $gender,
        ];
        if ('' !== $preferred_voice) {
            $voice_payload['name'] = $preferred_voice;
        }
        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'input' => ['text' => $text],
                'voice' => $voice_payload,
                'audioConfig' => [
                    'audioEncoding' => 'MP3',
                    'speakingRate' => 0.6,
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($body) || empty($body['audioContent'])) {
            wp_send_json_error(['message' => __('Google TTS failed to synthesize audio.', 'thai-literacy-app')], 500);
        }

        $audio_binary = base64_decode((string) $body['audioContent']);
        if (false === $audio_binary || '' === $audio_binary) {
            wp_send_json_error(['message' => __('Generated audio could not be decoded.', 'thai-literacy-app')], 500);
        }

        $prefix = function_exists('mb_substr') ? mb_substr($text, 0, 24) : substr($text, 0, 24);
        $base_name = sanitize_title($prefix);
        if ('' === $base_name) {
            $base_name = 'tts';
        }

        $attachment = $this->create_audio_attachment($post_id, $audio_binary, $base_name);
        if (is_wp_error($attachment)) {
            wp_send_json_error(['message' => $attachment->get_error_message()], 500);
        }

        wp_send_json_success([
            'url' => $attachment['url'],
            'attachment_id' => $attachment['attachment_id'],
            'field_key' => $field_key,
            'voice_gender' => strtolower($gender),
        ]);
    }

    public function ajax_delete_audio_attachment() {
        check_ajax_referer('tla_admin_audio', 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        if ($post_id > 0 && !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('You are not allowed to edit this post.', 'thai-literacy-app')], 403);
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $audio_url = esc_url_raw((string) wp_unslash($_POST['audio_url'] ?? ''));

        if (!$attachment_id && '' !== $audio_url) {
            $attachment_id = attachment_url_to_postid($audio_url);
        }

        if (!$attachment_id) {
            wp_send_json_success(['deleted' => false, 'message' => __('Audio was not found in Media Library.', 'thai-literacy-app')]);
        }

        if (!current_user_can('delete_post', $attachment_id)) {
            wp_send_json_error(['message' => __('You are not allowed to delete this audio file.', 'thai-literacy-app')], 403);
        }

        $deleted = wp_delete_attachment($attachment_id, true);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Could not delete audio from Media Library.', 'thai-literacy-app')], 500);
        }

        wp_send_json_success(['deleted' => true, 'attachment_id' => $attachment_id]);
    }

    public function render_shortcode($atts = []) {
        $atts = shortcode_atts(['post_id' => 0], $atts, 'thai_literacy_app');

        $post_id = absint($atts['post_id']);
        if (!$post_id && is_singular(self::POST_TYPE)) {
            $post_id = get_the_ID();
        }

        wp_enqueue_style('tla-app-style');
        wp_enqueue_script('tla-app-script');

        wp_localize_script('tla-app-script', 'TLA_APP', [
            'restUrl' => esc_url_raw(rest_url('tla/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
            'postId' => $post_id,
        ]);

        return sprintf(
            '<div id="tla-app-root" class="tla-app-root" data-rest-url="%1$s" data-nonce="%2$s" data-post-id="%3$d"></div>',
            esc_attr(esc_url_raw(rest_url('tla/v1'))),
            esc_attr(wp_create_nonce('wp_rest')),
            (int) $post_id
        );
    }

    public function add_admin_menu() {
        add_options_page(
            __('Thai Literacy App', 'thai-literacy-app'),
            __('Thai Literacy App', 'thai-literacy-app'),
            'manage_options',
            'thai-literacy-app',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('tla_settings_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => self::default_settings(),
        ]);
    }

    public function sanitize_settings($input) {
        $defaults = self::default_settings();
        $output = $defaults;

        $output['app_title'] = sanitize_text_field($input['app_title'] ?? $defaults['app_title']);
        $output['show_ipa'] = !empty($input['show_ipa']) ? 1 : 0;
        $output['default_mode'] = sanitize_key($input['default_mode'] ?? $defaults['default_mode']);

        $lesson_raw = $input['lesson_flow'] ?? '';
        if (is_string($lesson_raw)) {
            $output['lesson_flow'] = array_values(array_filter(array_map('trim', explode("\n", $lesson_raw))));
        }

        return $output;
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Thai Literacy App Settings', 'thai-literacy-app'); ?></h1>
            <p><?php esc_html_e('Create and edit content in the custom post type “Thai Literacy Games”.', 'thai-literacy-app'); ?></p>
            <p><?php esc_html_e('CSV template/download/upload tools are now available inside each individual game post.', 'thai-literacy-app'); ?></p>

            <h2><?php esc_html_e('Shortcode', 'thai-literacy-app'); ?></h2>
            <p><code>[thai_literacy_app post_id="123"]</code> <?php esc_html_e('Loads a specific game post anywhere.', 'thai-literacy-app'); ?></p>
            <p><code>[thai_literacy_app]</code> <?php esc_html_e('Auto-loads current game only when used on a single Thai Literacy Game post.', 'thai-literacy-app'); ?></p>
        </div>
        <?php
    }

    public function download_csv_template() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'thai-literacy-app'));
        }

        check_admin_referer('tla_csv_template');

        $filename = 'thai-literacy-template.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        echo "\xEF\xBB\xBF"; // UTF-8 BOM.

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'post_id',
            'post_title',
            'post_status',
            'game_mode',
            'app_title',
            'show_ipa',
            'default_mode',
            'lesson_flow_json',
            'content_json',
        ]);

        $sample = self::default_settings();
        fputcsv($output, [
            '',
            'Sample Thai Literacy Game',
            'publish',
            'syllable_decoder',
            $sample['app_title'],
            '1',
            'syllable_decoder',
            wp_json_encode($sample['lesson_flow'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            wp_json_encode($sample[self::DEFAULT_CONTENT_KEY], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        fclose($output);
        exit;
    }

    public function handle_csv_upload() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'thai-literacy-app'));
        }

        check_admin_referer('tla_csv_upload', 'tla_csv_upload_nonce');

        if (empty($_FILES['tla_csv_file']['tmp_name'])) {
            wp_safe_redirect(admin_url('options-general.php?page=thai-literacy-app'));
            exit;
        }

        $file = fopen($_FILES['tla_csv_file']['tmp_name'], 'r');
        if (!$file) {
            wp_safe_redirect(admin_url('options-general.php?page=thai-literacy-app'));
            exit;
        }

        $headers = fgetcsv($file);
        if ($headers && isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($headers, $row);
            if (empty($data['post_title']) && empty($data['post_id'])) {
                continue;
            }

            $postarr = [
                'post_type' => self::POST_TYPE,
                'post_status' => sanitize_key($data['post_status'] ?: 'draft'),
                'post_title' => sanitize_text_field($data['post_title'] ?? ''),
            ];

            if (!empty($data['post_id'])) {
                $postarr['ID'] = absint($data['post_id']);
            }

            $post_id = wp_insert_post($postarr);
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }

            update_post_meta($post_id, '_tla_game_mode', sanitize_key($data['game_mode'] ?? 'syllable_decoder'));
            update_post_meta($post_id, '_tla_app_title', sanitize_text_field($data['app_title'] ?? 'Learn to Read Thai'));
            update_post_meta($post_id, '_tla_show_ipa', !empty($data['show_ipa']) ? 1 : 0);
            update_post_meta($post_id, '_tla_default_mode', sanitize_key($data['default_mode'] ?? 'syllable_decoder'));

            $lesson_flow = json_decode($data['lesson_flow_json'] ?? '[]', true);
            if (!is_array($lesson_flow)) {
                $lesson_flow = [];
            }
            update_post_meta($post_id, '_tla_lesson_flow', $lesson_flow);

            $content_json = json_decode($data['content_json'] ?? '{}', true);
            if (!is_array($content_json)) {
                $content_json = self::default_settings()[self::DEFAULT_CONTENT_KEY];
            }
            update_post_meta($post_id, '_tla_content_json', wp_json_encode($content_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        fclose($file);
        wp_safe_redirect(admin_url('options-general.php?page=thai-literacy-app'));
        exit;
    }

    private function get_post_csv_headers() {
        return [
            'post_title',
            'post_status',
            'game_mode',
            'app_title',
            'show_ipa',
            'default_mode',
            'lesson_flow_json',
            'content_json',
        ];
    }

    private function get_post_csv_row($post_id) {
        $defaults = self::default_settings();

        $lesson_flow = get_post_meta($post_id, '_tla_lesson_flow', true);
        if (!is_array($lesson_flow)) {
            $lesson_flow = $defaults['lesson_flow'];
        }

        $content_json = get_post_meta($post_id, '_tla_content_json', true);
        $content = json_decode($content_json, true);
        if (!is_array($content)) {
            $content = $defaults[self::DEFAULT_CONTENT_KEY];
        }

        return [
            'post_title' => get_the_title($post_id),
            'post_status' => get_post_status($post_id) ?: 'draft',
            'game_mode' => get_post_meta($post_id, '_tla_game_mode', true) ?: 'syllable_decoder',
            'app_title' => get_post_meta($post_id, '_tla_app_title', true) ?: $defaults['app_title'],
            'show_ipa' => (string) ((int) ((bool) get_post_meta($post_id, '_tla_show_ipa', true))),
            'default_mode' => get_post_meta($post_id, '_tla_default_mode', true) ?: 'syllable_decoder',
            'lesson_flow_json' => wp_json_encode($lesson_flow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_json' => wp_json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function stream_single_post_csv($filename, $row_data) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        echo "\xEF\xBB\xBF"; // UTF-8 BOM.

        $output = fopen('php://output', 'w');
        fputcsv($output, $this->get_post_csv_headers());
        $header_ordered_row = [];
        foreach ($this->get_post_csv_headers() as $header) {
            $header_ordered_row[] = $row_data[$header] ?? '';
        }
        fputcsv($output, $header_ordered_row);
        fclose($output);
        exit;
    }

    private function get_post_csv_redirect_url($post_id, $status) {
        return add_query_arg(
            [
                'post' => (int) $post_id,
                'action' => 'edit',
                'tla_csv_status' => sanitize_key($status),
            ],
            admin_url('post.php')
        );
    }

    private function redirect_post_csv_status($post_id, $status) {
        wp_safe_redirect($this->get_post_csv_redirect_url($post_id, $status));
        exit;
    }

    private function apply_csv_row_to_post($post_id, $data) {
        if (!is_array($data)) {
            return 'invalid_csv';
        }

        $post_update = ['ID' => $post_id];
        $has_post_update = false;

        $title = isset($data['post_title']) ? trim((string) $data['post_title']) : '';
        if ('' !== $title) {
            $post_update['post_title'] = sanitize_text_field($title);
            $has_post_update = true;
        }

        $status = isset($data['post_status']) ? sanitize_key($data['post_status']) : '';
        $allowed_statuses = ['draft', 'publish', 'pending', 'private', 'future'];
        if (in_array($status, $allowed_statuses, true)) {
            $post_update['post_status'] = $status;
            $has_post_update = true;
        }

        if ($has_post_update) {
            $updated = wp_update_post($post_update, true);
            if (is_wp_error($updated)) {
                return 'update_failed';
            }
        }

        if (!empty($data['game_mode'])) {
            update_post_meta($post_id, '_tla_game_mode', sanitize_key($data['game_mode']));
        }

        if (!empty($data['app_title'])) {
            update_post_meta($post_id, '_tla_app_title', sanitize_text_field($data['app_title']));
        }

        if (isset($data['show_ipa']) && '' !== trim((string) $data['show_ipa'])) {
            $show_ipa_raw = strtolower(trim((string) $data['show_ipa']));
            $show_ipa = in_array($show_ipa_raw, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
            update_post_meta($post_id, '_tla_show_ipa', $show_ipa);
        }

        if (!empty($data['default_mode'])) {
            update_post_meta($post_id, '_tla_default_mode', sanitize_key($data['default_mode']));
        }

        if (isset($data['lesson_flow_json']) && '' !== trim((string) $data['lesson_flow_json'])) {
            $lesson_flow = json_decode($data['lesson_flow_json'], true);
            if (!is_array($lesson_flow)) {
                return 'invalid_lesson_json';
            }
            $lesson_flow = array_values(array_filter(array_map(static function ($step) {
                return is_string($step) ? trim($step) : '';
            }, $lesson_flow)));
            update_post_meta($post_id, '_tla_lesson_flow', $lesson_flow);
        }

        if (isset($data['content_json']) && '' !== trim((string) $data['content_json'])) {
            $content = json_decode($data['content_json'], true);
            if (!is_array($content)) {
                return 'invalid_content_json';
            }
            update_post_meta($post_id, '_tla_content_json', wp_json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return 'success';
    }

    public function render_post_csv_notice() {
        $status = sanitize_key($_GET['tla_csv_status'] ?? '');
        if ('' === $status) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || 'post' !== $screen->base || self::POST_TYPE !== $screen->post_type) {
            return;
        }

        $messages = [
            'success' => __('CSV uploaded and this post was updated.', 'thai-literacy-app'),
            'missing_file' => __('Please choose a CSV file first.', 'thai-literacy-app'),
            'invalid_file' => __('Unable to open the uploaded CSV file.', 'thai-literacy-app'),
            'no_rows' => __('CSV must include a header row and one data row.', 'thai-literacy-app'),
            'invalid_csv' => __('CSV format is invalid. Ensure headers match the template.', 'thai-literacy-app'),
            'invalid_lesson_json' => __('lesson_flow_json must be valid JSON array.', 'thai-literacy-app'),
            'invalid_content_json' => __('content_json must be valid JSON object.', 'thai-literacy-app'),
            'update_failed' => __('Unable to update this post from CSV.', 'thai-literacy-app'),
        ];

        if (!isset($messages[$status])) {
            return;
        }

        $error_statuses = ['missing_file', 'invalid_file', 'no_rows', 'invalid_csv', 'invalid_lesson_json', 'invalid_content_json', 'update_failed'];
        $notice_class = in_array($status, $error_statuses, true) ? 'notice notice-error' : 'notice notice-success';

        printf(
            '<div class="%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($notice_class),
            esc_html($messages[$status])
        );
    }

    public function download_post_csv_template() {
        $post_id = absint($_GET['post_id'] ?? 0);
        if (!$post_id || self::POST_TYPE !== get_post_type($post_id)) {
            wp_die(esc_html__('Invalid game post.', 'thai-literacy-app'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('Unauthorized', 'thai-literacy-app'));
        }

        check_admin_referer('tla_post_csv_template_' . $post_id);

        $sample = self::default_settings();
        $sample_row = [
            'post_title' => 'Sample Thai Literacy Game',
            'post_status' => 'draft',
            'game_mode' => 'syllable_decoder',
            'app_title' => $sample['app_title'],
            'show_ipa' => '1',
            'default_mode' => 'syllable_decoder',
            'lesson_flow_json' => wp_json_encode($sample['lesson_flow'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_json' => wp_json_encode($sample[self::DEFAULT_CONTENT_KEY], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $this->stream_single_post_csv('thai-literacy-post-template-' . $post_id . '.csv', $sample_row);
    }

    public function download_post_csv() {
        $post_id = absint($_GET['post_id'] ?? 0);
        if (!$post_id || self::POST_TYPE !== get_post_type($post_id)) {
            wp_die(esc_html__('Invalid game post.', 'thai-literacy-app'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('Unauthorized', 'thai-literacy-app'));
        }

        check_admin_referer('tla_post_csv_download_' . $post_id);

        $post_slug = sanitize_title(get_the_title($post_id));
        if ('' === $post_slug) {
            $post_slug = 'game';
        }

        $filename = sprintf('thai-literacy-post-%d-%s.csv', $post_id, $post_slug);
        $this->stream_single_post_csv($filename, $this->get_post_csv_row($post_id));
    }

    public function handle_post_csv_upload() {
        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id || self::POST_TYPE !== get_post_type($post_id)) {
            wp_die(esc_html__('Invalid game post.', 'thai-literacy-app'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('Unauthorized', 'thai-literacy-app'));
        }

        check_admin_referer('tla_post_csv_upload_' . $post_id, 'tla_post_csv_upload_nonce');

        if (empty($_FILES['tla_csv_file']['tmp_name'])) {
            $this->redirect_post_csv_status($post_id, 'missing_file');
        }

        $file = fopen($_FILES['tla_csv_file']['tmp_name'], 'r');
        if (!$file) {
            $this->redirect_post_csv_status($post_id, 'invalid_file');
        }

        $headers = fgetcsv($file);
        if ($headers && isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        $row = fgetcsv($file);
        fclose($file);

        if (!is_array($headers) || empty($headers) || !is_array($row)) {
            $this->redirect_post_csv_status($post_id, 'no_rows');
        }

        if (count($row) !== count($headers)) {
            $header_count = count($headers);
            $row = array_slice(array_pad($row, $header_count, ''), 0, $header_count);
        }

        $data = array_combine($headers, $row);
        $status = $this->apply_csv_row_to_post($post_id, $data);
        $this->redirect_post_csv_status($post_id, $status);
    }

    public function register_rest_routes() {
        register_rest_route('tla/v1', '/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_config'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => ['required' => false, 'type' => 'integer'],
            ],
        ]);

        register_rest_route('tla/v1', '/attempt', [
            'methods' => 'POST',
            'callback' => [$this, 'record_attempt'],
            'permission_callback' => '__return_true',
            'args' => [
                'item_id' => ['required' => true, 'type' => 'string'],
                'mode' => ['required' => true, 'type' => 'string'],
                'correct' => ['required' => true, 'type' => 'boolean'],
                'error_tags' => ['required' => false, 'type' => 'array'],
                'latency_ms' => ['required' => false, 'type' => 'integer'],
            ],
        ]);
    }

    public function get_config(WP_REST_Request $request) {
        $post_id = absint($request->get_param('post_id'));

        if (!$post_id) {
            $posts = get_posts([
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => 1,
                'fields' => 'ids',
            ]);
            $post_id = !empty($posts) ? (int) $posts[0] : 0;
        }

        if (!$post_id) {
            $settings = self::default_settings();
            return rest_ensure_response([
                'app_title' => $settings['app_title'],
                'show_ipa' => (bool) $settings['show_ipa'],
                'default_mode' => $settings['default_mode'],
                'lesson_flow' => $settings['lesson_flow'],
                'content' => $settings[self::DEFAULT_CONTENT_KEY],
                'post_id' => 0,
            ]);
        }

        $content_json = get_post_meta($post_id, '_tla_content_json', true);
        $content = json_decode($content_json, true);
        if (!is_array($content)) {
            $content = self::default_settings()[self::DEFAULT_CONTENT_KEY];
        }

        $lesson_flow = get_post_meta($post_id, '_tla_lesson_flow', true);
        if (!is_array($lesson_flow)) {
            $lesson_flow = self::default_settings()['lesson_flow'];
        }

        return rest_ensure_response([
            'app_title' => get_post_meta($post_id, '_tla_app_title', true) ?: get_the_title($post_id),
            'show_ipa' => (bool) get_post_meta($post_id, '_tla_show_ipa', true),
            'default_mode' => get_post_meta($post_id, '_tla_default_mode', true) ?: 'syllable_decoder',
            'lesson_flow' => $lesson_flow,
            'content' => $content,
            'post_id' => $post_id,
        ]);
    }

    public function record_attempt(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tla_attempts';

        $error_tags = $request->get_param('error_tags');
        $stored_tags = is_array($error_tags) ? wp_json_encode(array_values($error_tags)) : null;

        $wpdb->insert(
            $table_name,
            [
                'user_id' => get_current_user_id(),
                'item_id' => sanitize_text_field($request->get_param('item_id')),
                'mode' => sanitize_key($request->get_param('mode')),
                'correct' => $request->get_param('correct') ? 1 : 0,
                'error_tags' => $stored_tags,
                'latency_ms' => absint($request->get_param('latency_ms')),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%d', '%s']
        );

        return rest_ensure_response(['saved' => true]);
    }
}
