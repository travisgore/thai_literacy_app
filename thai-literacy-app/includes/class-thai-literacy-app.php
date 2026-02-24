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

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_tla_download_csv_template', [$this, 'download_csv_template']);
        add_action('admin_post_tla_upload_csv', [$this, 'handle_csv_upload']);

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
            'app_title' => 'Thai Reading Literacy Trainer',
            'show_ipa' => 1,
            'default_mode' => 'syllable_decoder',
            'lesson_flow' => [
                'Script orientation',
                'Consonant recognition + aspiration',
                'Consonant class mastery',
                'Simple vowels + placements',
                'Syllable structure + inherent vowels',
                'Live/dead + final consonant collapse',
                'Tone marks + decision table',
                'Reduced spellings + alternations',
                'Clusters + tone helpers + silent letters',
                'High-frequency reading drills',
                'Real-world reading tasks',
            ],
            self::DEFAULT_CONTENT_KEY => [
                'graphemes' => [
                    ['id' => 'cons_k_mid', 'glyph' => 'ก', 'type' => 'consonant', 'class' => 'mid', 'ipa_initial' => 'k', 'ipa_final' => 'k̚', 'hint' => 'Unaspirated /k/ like English "sk" in sky'],
                    ['id' => 'cons_kh_high', 'glyph' => 'ข', 'type' => 'consonant', 'class' => 'high', 'ipa_initial' => 'kʰ', 'ipa_final' => 'k̚', 'hint' => 'Aspirated /kʰ/ with a strong puff of air'],
                    ['id' => 'vowel_e_short', 'glyph' => 'เ◌ะ', 'type' => 'vowel_pattern', 'class' => 'pattern', 'ipa_initial' => 'e', 'ipa_final' => '', 'hint' => 'Short e, often reduced to เ◌็C in closed syllables'],
                    ['id' => 'tone_mai_ek', 'glyph' => '่', 'type' => 'tone_mark', 'class' => 'mark', 'ipa_initial' => '', 'ipa_final' => '', 'hint' => 'mai ek'],
                ],
                'syllables' => [
                    ['id' => 'sy_ka_mid', 'text' => 'กา', 'initial_class' => 'mid', 'tone_mark' => 'none', 'syllable_type' => 'live', 'resulting_tone' => 'mid', 'ipa' => 'kaː', 'meaning' => 'crow'],
                    ['id' => 'sy_khai', 'text' => 'ไข่', 'initial_class' => 'high', 'tone_mark' => 'mai_ek', 'syllable_type' => 'live', 'resulting_tone' => 'low', 'ipa' => 'kʰàj', 'meaning' => 'egg'],
                    ['id' => 'sy_nam', 'text' => 'น้ำ', 'initial_class' => 'low', 'tone_mark' => 'mai_tho', 'syllable_type' => 'dead_short', 'resulting_tone' => 'high', 'ipa' => 'nám', 'meaning' => 'water'],
                ],
                'vowel_families' => [
                    ['id' => 'vf_e_family', 'label' => 'เ◌ะ / เ◌็C / เ◌', 'target_ipa' => 'e/eː', 'notes' => 'Short and long e family'],
                    ['id' => 'vf_uea_family', 'label' => 'เ◌ือะ / เ◌ือ', 'target_ipa' => 'ɯa', 'notes' => 'Complex pre+above vowel family'],
                ],
                'tone_rules' => [
                    ['initial_class' => 'low', 'tone_mark' => 'none', 'syllable_type' => 'live', 'resulting_tone' => 'mid'],
                    ['initial_class' => 'low', 'tone_mark' => 'none', 'syllable_type' => 'dead_short', 'resulting_tone' => 'high'],
                    ['initial_class' => 'low', 'tone_mark' => 'none', 'syllable_type' => 'dead_long', 'resulting_tone' => 'falling'],
                    ['initial_class' => 'mid', 'tone_mark' => 'none', 'syllable_type' => 'live', 'resulting_tone' => 'mid'],
                    ['initial_class' => 'mid', 'tone_mark' => 'none', 'syllable_type' => 'dead_short', 'resulting_tone' => 'low'],
                    ['initial_class' => 'mid', 'tone_mark' => 'none', 'syllable_type' => 'dead_long', 'resulting_tone' => 'low'],
                    ['initial_class' => 'high', 'tone_mark' => 'none', 'syllable_type' => 'live', 'resulting_tone' => 'rising'],
                    ['initial_class' => 'high', 'tone_mark' => 'none', 'syllable_type' => 'dead_short', 'resulting_tone' => 'low'],
                    ['initial_class' => 'high', 'tone_mark' => 'none', 'syllable_type' => 'dead_long', 'resulting_tone' => 'low'],
                    ['initial_class' => 'low', 'tone_mark' => 'mai_ek', 'syllable_type' => 'any', 'resulting_tone' => 'falling'],
                    ['initial_class' => 'mid', 'tone_mark' => 'mai_ek', 'syllable_type' => 'any', 'resulting_tone' => 'low'],
                    ['initial_class' => 'high', 'tone_mark' => 'mai_ek', 'syllable_type' => 'any', 'resulting_tone' => 'low'],
                    ['initial_class' => 'low', 'tone_mark' => 'mai_tho', 'syllable_type' => 'any', 'resulting_tone' => 'high'],
                    ['initial_class' => 'mid', 'tone_mark' => 'mai_tho', 'syllable_type' => 'any', 'resulting_tone' => 'falling'],
                    ['initial_class' => 'high', 'tone_mark' => 'mai_tho', 'syllable_type' => 'any', 'resulting_tone' => 'falling'],
                    ['initial_class' => 'mid', 'tone_mark' => 'mai_tri', 'syllable_type' => 'any', 'resulting_tone' => 'high'],
                    ['initial_class' => 'mid', 'tone_mark' => 'mai_chattawa', 'syllable_type' => 'any', 'resulting_tone' => 'rising'],
                ],
                'games' => [
                    ['id' => 'syllable_decoder', 'name' => 'Syllable Decoder', 'description' => 'Read Thai syllables and identify tone outcomes.'],
                    ['id' => 'grapheme_snap', 'name' => 'Grapheme Snap', 'description' => 'Match grapheme to sound or category quickly.'],
                    ['id' => 'tone_calculator', 'name' => 'Tone Calculator Duel', 'description' => 'Compute tone from class + mark + syllable type.'],
                    ['id' => 'vowel_assembler', 'name' => 'Vowel Pattern Assembler', 'description' => 'Build a vowel family around a consonant slot.'],
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
            __('Thai Literacy Game Configuration', 'thai-literacy-app'),
            [$this, 'render_game_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_game_meta_box($post) {
        wp_nonce_field('tla_save_game_meta', 'tla_game_meta_nonce');

        $game_mode = get_post_meta($post->ID, '_tla_game_mode', true) ?: 'syllable_decoder';
        $app_title = get_post_meta($post->ID, '_tla_app_title', true) ?: 'Thai Reading Literacy Trainer';
        $show_ipa = (int) get_post_meta($post->ID, '_tla_show_ipa', true);
        $default_mode = get_post_meta($post->ID, '_tla_default_mode', true) ?: 'syllable_decoder';
        $lesson_flow = get_post_meta($post->ID, '_tla_lesson_flow', true);
        $content_json = get_post_meta($post->ID, '_tla_content_json', true);

        if (empty($lesson_flow)) {
            $lesson_flow = self::default_settings()['lesson_flow'];
        }

        if (empty($content_json)) {
            $content_json = wp_json_encode(self::default_settings()[self::DEFAULT_CONTENT_KEY], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $decoded = json_decode($content_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $content_json = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        ?>
        <p>
            <label for="tla_game_mode"><strong><?php esc_html_e('Primary Game Mode', 'thai-literacy-app'); ?></strong></label><br />
            <select id="tla_game_mode" name="tla_game_mode">
                <option value="syllable_decoder" <?php selected($game_mode, 'syllable_decoder'); ?>>Syllable Decoder</option>
                <option value="tone_calculator" <?php selected($game_mode, 'tone_calculator'); ?>>Tone Calculator</option>
                <option value="grapheme_snap" <?php selected($game_mode, 'grapheme_snap'); ?>>Grapheme Snap</option>
                <option value="vowel_assembler" <?php selected($game_mode, 'vowel_assembler'); ?>>Vowel Assembler</option>
            </select>
        </p>
        <p>
            <label for="tla_app_title"><strong><?php esc_html_e('App Title', 'thai-literacy-app'); ?></strong></label><br />
            <input id="tla_app_title" type="text" name="tla_app_title" value="<?php echo esc_attr($app_title); ?>" class="regular-text" />
        </p>
        <p>
            <label for="tla_default_mode"><strong><?php esc_html_e('Default Mode', 'thai-literacy-app'); ?></strong></label><br />
            <select id="tla_default_mode" name="tla_default_mode">
                <option value="syllable_decoder" <?php selected($default_mode, 'syllable_decoder'); ?>>Syllable Decoder</option>
                <option value="tone_calculator" <?php selected($default_mode, 'tone_calculator'); ?>>Tone Calculator</option>
                <option value="grapheme_snap" <?php selected($default_mode, 'grapheme_snap'); ?>>Grapheme Snap</option>
                <option value="vowel_assembler" <?php selected($default_mode, 'vowel_assembler'); ?>>Vowel Assembler</option>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="tla_show_ipa" value="1" <?php checked($show_ipa, 1); ?> />
                <?php esc_html_e('Show IPA hints', 'thai-literacy-app'); ?>
            </label>
        </p>
        <p>
            <label for="tla_lesson_flow"><strong><?php esc_html_e('Lesson Flow (one per line)', 'thai-literacy-app'); ?></strong></label><br />
            <textarea id="tla_lesson_flow" name="tla_lesson_flow" rows="8" style="width:100%;"><?php echo esc_textarea(implode("\n", $lesson_flow)); ?></textarea>
        </p>
        <p>
            <label for="tla_content_json"><strong><?php esc_html_e('Content JSON (UTF-8)', 'thai-literacy-app'); ?></strong></label><br />
            <textarea id="tla_content_json" name="tla_content_json" rows="20" class="code" style="width:100%;"><?php echo esc_textarea($content_json); ?></textarea>
        </p>
        <p class="description"><?php esc_html_e('Use the CSV template under Settings → Thai Literacy App to bulk create/update posts while preserving Thai characters.', 'thai-literacy-app'); ?></p>
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
        $app_title = sanitize_text_field($_POST['tla_app_title'] ?? 'Thai Reading Literacy Trainer');
        $show_ipa = !empty($_POST['tla_show_ipa']) ? 1 : 0;
        $default_mode = sanitize_key($_POST['tla_default_mode'] ?? 'syllable_decoder');

        $lesson_raw = sanitize_textarea_field($_POST['tla_lesson_flow'] ?? '');
        $lesson_flow = array_values(array_filter(array_map('trim', explode("\n", $lesson_raw))));

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

        return '<div id="tla-app-root" class="tla-app-root"></div>';
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
        $settings = get_option(self::OPTION_KEY, self::default_settings());
        $download_url = wp_nonce_url(admin_url('admin-post.php?action=tla_download_csv_template'), 'tla_csv_template');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Thai Literacy App Settings', 'thai-literacy-app'); ?></h1>
            <p><?php esc_html_e('Use the custom post type “Thai Literacy Games” to create game content. Use CSV tools below for bulk operations.', 'thai-literacy-app'); ?></p>

            <h2><?php esc_html_e('CSV template (UTF-8 Thai-safe)', 'thai-literacy-app'); ?></h2>
            <p><a href="<?php echo esc_url($download_url); ?>" class="button button-secondary"><?php esc_html_e('Download CSV Template', 'thai-literacy-app'); ?></a></p>

            <h2><?php esc_html_e('Bulk CSV Upload', 'thai-literacy-app'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('tla_csv_upload', 'tla_csv_upload_nonce'); ?>
                <input type="hidden" name="action" value="tla_upload_csv" />
                <input type="file" name="tla_csv_file" accept=".csv,text/csv" required />
                <?php submit_button(__('Upload CSV', 'thai-literacy-app'), 'primary', 'submit', false); ?>
            </form>

            <h2><?php esc_html_e('Shortcode', 'thai-literacy-app'); ?></h2>
            <p><code>[thai_literacy_app]</code> <?php esc_html_e('Auto-loads current Thai Literacy Game post when used on that post type.', 'thai-literacy-app'); ?></p>
            <p><code>[thai_literacy_app post_id="123"]</code> <?php esc_html_e('Loads a specific game post anywhere.', 'thai-literacy-app'); ?></p>
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
            update_post_meta($post_id, '_tla_app_title', sanitize_text_field($data['app_title'] ?? 'Thai Reading Literacy Trainer'));
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
