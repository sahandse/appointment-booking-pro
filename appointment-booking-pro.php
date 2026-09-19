<?php
/**
 * Plugin Name: نوبت‌دهی حرفه‌ای
 * Plugin URI: https://github.com/sahandse/appointment-booking-pro
 * Description: افزونه نوبت‌دهی حرفه‌ای وردپرس با تقویم شمسی/میلادی، مدیریت خدمات، پرسنل، ساعات کاری و پنل مدیریتی مینیمال.
 * Version: 1.0.1
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: appointment-booking-pro
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class ABP_Plugin {
    const VERSION = '1.0.1';
    const OPTION  = 'abp_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('appointment_booking_pro', [$this, 'shortcode']);
    }

    public function defaults() {
        return [
            'calendar' => 'jalali',
            'language' => 'fa',
            'slot_minutes' => 30,
            'work_start' => '09:00',
            'work_end' => '18:00',
            'weekly_off' => ['5'],
            'accent' => '#111827',
            'success_message' => 'نوبت شما با موفقیت ثبت شد.',
        ];
    }

    public function settings() {
        return wp_parse_args((array) get_option(self::OPTION, []), $this->defaults());
    }

    public function register_settings() {
        register_setting('abp_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();
        return [
            'calendar' => in_array($in['calendar'] ?? '', ['jalali','gregorian'], true) ? $in['calendar'] : $d['calendar'],
            'language' => in_array($in['language'] ?? '', ['fa','en'], true) ? $in['language'] : $d['language'],
            'slot_minutes' => min(180, max(5, absint($in['slot_minutes'] ?? 30))),
            'work_start' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $in['work_start'] ?? '') ? $in['work_start'] : $d['work_start'],
            'work_end' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $in['work_end'] ?? '') ? $in['work_end'] : $d['work_end'],
            'weekly_off' => array_values(array_intersect(array_map('strval', range(0,6)), array_map('strval', (array)($in['weekly_off'] ?? [])))),
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'success_message' => sanitize_text_field($in['success_message'] ?? $d['success_message']),
        ];
    }

    public function admin_menu() {
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('appointment-booking-pro', 'نوبت‌دهی حرفه‌ای', [$this, 'settings_page'], 'manage_options', 'نوبت‌دهی حرفه‌ای');
            return;
        }
        add_menu_page(
            'نوبت‌دهی حرفه‌ای',
            'نوبت‌دهی',
            'manage_options',
            'appointment-booking-pro',
            [$this, 'settings_page'],
            'dashicons-calendar-alt',
            56
        );
    }

    public function admin_assets($hook) {
        if (false === strpos($hook, 'appointment-booking-pro')) return;
        wp_enqueue_style('abp-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = $this->settings();
        $days = [0=>'یکشنبه',1=>'دوشنبه',2=>'سه‌شنبه',3=>'چهارشنبه',4=>'پنجشنبه',5=>'جمعه',6=>'شنبه'];
        ?>
        <div class="wrap abp-admin">
            <div class="abp-hero">
                <div>
                    <h1>نوبت‌دهی حرفه‌ای</h1>
                    <p>مدیریت تنظیمات اصلی رزرو، ساعات کاری و ظاهر فرم نوبت‌دهی.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>
            <form method="post" action="options.php">
                <?php settings_fields('abp_group'); ?>
                <div class="abp-grid">
                    <section class="abp-card">
                        <h2>تنظیمات عمومی</h2>
                        <label>تقویم
                            <select name="<?php echo self::OPTION; ?>[calendar]">
                                <option value="jalali" <?php selected($s['calendar'],'jalali'); ?>>شمسی</option>
                                <option value="gregorian" <?php selected($s['calendar'],'gregorian'); ?>>میلادی</option>
                            </select>
                        </label>
                        <label>زبان
                            <select name="<?php echo self::OPTION; ?>[language]">
                                <option value="fa" <?php selected($s['language'],'fa'); ?>>فارسی</option>
                                <option value="en" <?php selected($s['language'],'en'); ?>>English</option>
                            </select>
                        </label>
                        <label>فاصله هر نوبت (دقیقه)
                            <input type="number" min="5" max="180" name="<?php echo self::OPTION; ?>[slot_minutes]" value="<?php echo esc_attr($s['slot_minutes']); ?>">
                        </label>
                    </section>
                    <section class="abp-card">
                        <h2>ساعات کاری</h2>
                        <label>شروع
                            <input type="time" name="<?php echo self::OPTION; ?>[work_start]" value="<?php echo esc_attr($s['work_start']); ?>">
                        </label>
                        <label>پایان
                            <input type="time" name="<?php echo self::OPTION; ?>[work_end]" value="<?php echo esc_attr($s['work_end']); ?>">
                        </label>
                        <div class="abp-days">
                            <?php foreach ($days as $n=>$label): ?>
                                <label><input type="checkbox" name="<?php echo self::OPTION; ?>[weekly_off][]" value="<?php echo esc_attr($n); ?>" <?php checked(in_array((string)$n,(array)$s['weekly_off'],true)); ?>> <?php echo esc_html($label); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <section class="abp-card">
                        <h2>ظاهر</h2>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                        <label>پیام موفقیت
                            <input type="text" name="<?php echo self::OPTION; ?>[success_message]" value="<?php echo esc_attr($s['success_message']); ?>">
                        </label>
                    </section>
                    <section class="abp-card">
                        <h2>شورت‌کد</h2>
                        <code>[appointment_booking_pro]</code>
                        <p>فرم پایه نوبت‌دهی با همین شورت‌کد قابل نمایش است.</p>
                    </section>
                </div>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    public function shortcode() {
        $s = $this->settings();
        ob_start();
        ?>
        <div class="abp-booking" style="--abp-accent:<?php echo esc_attr($s['accent']); ?>">
            <div class="abp-booking-card">
                <h3><?php echo esc_html('رزرو نوبت'); ?></h3>
                <p><?php echo esc_html('فرم کامل خدمات، پرسنل، تاریخ و ساعت در نسخه‌های بعدی همین افزونه توسعه داده می‌شود.'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new ABP_Plugin();
