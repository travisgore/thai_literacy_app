<?php

if (!defined('ABSPATH')) {
    exit;
}

class Thai_Literacy_App {
    const OPTION_KEY = 'tla_settings';
    const DEFAULT_CONTENT_KEY = 'default_content';

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('thai_literacy_app', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public static function activate() {
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
                    ['id' => 'grapheme_snap', 'name' => 'Grapheme Snap', 'description' => 'Match grapheme to sound or category quickly.'],
                    ['id' => 'tone_calculator', 'name' => 'Tone Calculator Duel', 'description' => 'Compute tone from class + mark + syllable type.'],
                    ['id' => 'vowel_assembler', 'name' => 'Vowel Pattern Assembler', 'description' => 'Build a vowel family around a consonant slot.'],
                ],
            ],
        ];
    }

    public function register_assets() {
        wp_register_style('tla-app-style', TLA_PLUGIN_URL . 'assets/css/app.css', [], TLA_PLUGIN_VERSION);
        wp_register_script('tla-app-script', TLA_PLUGIN_URL . 'assets/js/app.js', [], TLA_PLUGIN_VERSION, true);
    }

    public function render_shortcode() {
        wp_enqueue_style('tla-app-style');
        wp_enqueue_script('tla-app-script');

        wp_localize_script('tla-app-script', 'TLA_APP', [
            'restUrl' => esc_url_raw(rest_url('tla/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
        ]);

        ob_start();
        ?>
        <div id="tla-app-root" class="tla-app-root"></div>
        <?php
        return ob_get_clean();
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

        $json_raw = $input[self::DEFAULT_CONTENT_KEY] ?? '';
        if (is_string($json_raw)) {
            $decoded = json_decode(wp_unslash($json_raw), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $output[self::DEFAULT_CONTENT_KEY] = $decoded;
            }
        }

        return $output;
    }

    public function render_settings_page() {
        $settings = get_option(self::OPTION_KEY, self::default_settings());
        $lesson_text = implode("\n", $settings['lesson_flow']);
        $content_json = wp_json_encode($settings[self::DEFAULT_CONTENT_KEY], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Thai Literacy App Settings', 'thai-literacy-app'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('tla_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tla_app_title"><?php esc_html_e('App Title', 'thai-literacy-app'); ?></label></th>
                        <td><input id="tla_app_title" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[app_title]" value="<?php echo esc_attr($settings['app_title']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Show IPA', 'thai-literacy-app'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_ipa]" value="1" <?php checked($settings['show_ipa'], 1); ?> /> <?php esc_html_e('Display IPA hints in drills', 'thai-literacy-app'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tla_default_mode"><?php esc_html_e('Default Mode', 'thai-literacy-app'); ?></label></th>
                        <td>
                            <select id="tla_default_mode" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_mode]">
                                <option value="syllable_decoder" <?php selected($settings['default_mode'], 'syllable_decoder'); ?>>Syllable Decoder</option>
                                <option value="tone_calculator" <?php selected($settings['default_mode'], 'tone_calculator'); ?>>Tone Calculator</option>
                                <option value="grapheme_snap" <?php selected($settings['default_mode'], 'grapheme_snap'); ?>>Grapheme Snap</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tla_lesson_flow"><?php esc_html_e('Lesson Flow (one step per line)', 'thai-literacy-app'); ?></label></th>
                        <td><textarea id="tla_lesson_flow" name="<?php echo esc_attr(self::OPTION_KEY); ?>[lesson_flow]" rows="10" cols="70"><?php echo esc_textarea($lesson_text); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tla_default_content"><?php esc_html_e('Content JSON', 'thai-literacy-app'); ?></label></th>
                        <td>
                            <p class="description"><?php esc_html_e('Paste JSON to control graphemes, syllables, vowel families, tone rules, and games.', 'thai-literacy-app'); ?></p>
                            <textarea id="tla_default_content" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_content]" rows="28" cols="110" class="code"><?php echo esc_textarea($content_json); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p><strong><?php esc_html_e('Usage:', 'thai-literacy-app'); ?></strong> <?php esc_html_e('Place shortcode [thai_literacy_app] on any page.', 'thai-literacy-app'); ?></p>
        </div>
        <?php
    }

    public function register_rest_routes() {
        register_rest_route('tla/v1', '/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_config'],
            'permission_callback' => '__return_true',
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

    public function get_config() {
        $settings = get_option(self::OPTION_KEY, self::default_settings());

        return rest_ensure_response([
            'app_title' => $settings['app_title'],
            'show_ipa' => (bool) $settings['show_ipa'],
            'default_mode' => $settings['default_mode'],
            'lesson_flow' => $settings['lesson_flow'],
            'content' => $settings[self::DEFAULT_CONTENT_KEY],
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
