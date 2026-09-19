<?php
/**
 * Plugin Name: نوبت‌دهی حرفه‌ای
 * Plugin URI: https://github.com/sahandse/appointment-booking-pro
 * Description: افزونه نوبت‌دهی حرفه‌ای وردپرس با تقویم شمسی/میلادی، مدیریت خدمات، پرسنل، ساعات کاری و پنل مدیریتی مینیمال.
 * Version: 1.1.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: appointment-booking-pro
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class ABP_Plugin {
    const VERSION = '1.1.0';
    const OPTION  = 'abp_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('appointment_booking_pro', [$this, 'shortcode']);
        add_action('init', [$this, 'register_booking_cpt']);
        add_action('admin_post_nopriv_abp_submit_booking', [$this, 'submit_booking']);
        add_action('admin_post_abp_submit_booking', [$this, 'submit_booking']);
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
            'services' => "مشاوره|300000\nویزیت|500000",
            'staff' => "پرسنل ۱\nپرسنل ۲",
            'min_notice_hours' => 2,
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
            'services' => sanitize_textarea_field($in['services'] ?? $d['services']),
            'staff' => sanitize_textarea_field($in['staff'] ?? $d['staff']),
            'min_notice_hours' => min(168, max(0, absint($in['min_notice_hours'] ?? 2))),
        ];
    }

    public function admin_menu() {
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('appointment-booking-pro', 'نوبت‌دهی حرفه‌ای', [$this, 'settings_page'], 'manage_options', 'نوبت‌دهی حرفه‌ای');
            add_submenu_page('s-store','نوبت‌ها','↳ نوبت‌ها','manage_options','edit.php?post_type=abp_booking');
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
        add_submenu_page('appointment-booking-pro','نوبت‌ها','نوبت‌ها','manage_options','edit.php?post_type=abp_booking');
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
                        <h2>خدمات و پرسنل</h2>
                        <label>خدمات (هر خط: نام|قیمت)
                            <textarea rows="7" name="<?php echo self::OPTION; ?>[services]"><?php echo esc_textarea($s['services']); ?></textarea>
                        </label>
                        <label>پرسنل (هر خط یک نام)
                            <textarea rows="5" name="<?php echo self::OPTION; ?>[staff]"><?php echo esc_textarea($s['staff']); ?></textarea>
                        </label>
                        <label>حداقل فاصله رزرو از زمان فعلی (ساعت)
                            <input type="number" min="0" max="168" name="<?php echo self::OPTION; ?>[min_notice_hours]" value="<?php echo esc_attr($s['min_notice_hours']); ?>">
                        </label>
                    </section>
                    <section class="abp-card">
                        <h2>شورت‌کد</h2>
                        <code>[appointment_booking_pro]</code>
                        <p>فرم کامل ثبت نوبت، انتخاب خدمت، پرسنل، تاریخ و ساعت.</p>
                    </section>
                </div>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    public function register_booking_cpt() {
        register_post_type('abp_booking', [
            'labels' => ['name'=>'نوبت‌ها','singular_name'=>'نوبت','edit_item'=>'مشاهده نوبت'],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }

    private function parse_services($raw) {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)$raw) as $line) {
            $line = trim($line);
            if (!$line) continue;
            [$name,$price] = array_pad(array_map('trim', explode('|',$line,2)),2,'');
            $out[] = ['name'=>$name,'price'=>$price];
        }
        return $out;
    }

    private function parse_staff($raw) {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$raw))));
    }

    private function slots($s) {
        $slots=[];
        $start=strtotime('1970-01-01 '.$s['work_start'].':00');
        $end=strtotime('1970-01-01 '.$s['work_end'].':00');
        $step=max(5,(int)$s['slot_minutes'])*60;
        for($t=$start;$t<$end;$t+=$step) $slots[]=gmdate('H:i',$t);
        return $slots;
    }

    public function submit_booking() {
        if (!isset($_POST['abp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abp_nonce'])),'abp_submit_booking')) wp_die('درخواست نامعتبر.');
        $s=$this->settings();
        $name=sanitize_text_field(wp_unslash($_POST['name']??''));
        $phone=preg_replace('/[^0-9+]/','',wp_unslash($_POST['phone']??''));
        $service=sanitize_text_field(wp_unslash($_POST['service']??''));
        $staff=sanitize_text_field(wp_unslash($_POST['staff']??''));
        $date=sanitize_text_field(wp_unslash($_POST['date']??''));
        $time=sanitize_text_field(wp_unslash($_POST['time']??''));

        $validServices=array_column($this->parse_services($s['services']),'name');
        $validStaff=$this->parse_staff($s['staff']);
        if(!$name||!$phone||!in_array($service,$validServices,true)||!in_array($staff,$validStaff,true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!in_array($time,$this->slots($s),true)) {
            wp_safe_redirect(add_query_arg('abp_error','invalid',wp_get_referer()?:home_url('/'))); exit;
        }

        $stamp=strtotime($date.' '.$time);
        if(!$stamp || $stamp < current_time('timestamp') + ((int)$s['min_notice_hours']*HOUR_IN_SECONDS)) {
            wp_safe_redirect(add_query_arg('abp_error','time',wp_get_referer()?:home_url('/'))); exit;
        }

        $weekday=(string)date('w',$stamp);
        if(in_array($weekday,(array)$s['weekly_off'],true)) {
            wp_safe_redirect(add_query_arg('abp_error','off',wp_get_referer()?:home_url('/'))); exit;
        }

        $existing=get_posts(['post_type'=>'abp_booking','post_status'=>'publish','numberposts'=>1,'meta_query'=>[
            ['key'=>'_abp_staff','value'=>$staff],
            ['key'=>'_abp_date','value'=>$date],
            ['key'=>'_abp_time','value'=>$time],
        ]]);
        if($existing) { wp_safe_redirect(add_query_arg('abp_error','busy',wp_get_referer()?:home_url('/'))); exit; }

        $id=wp_insert_post(['post_type'=>'abp_booking','post_status'=>'publish','post_title'=>$name.' - '.$date.' '.$time]);
        if($id){
            update_post_meta($id,'_abp_name',$name); update_post_meta($id,'_abp_phone',$phone);
            update_post_meta($id,'_abp_service',$service); update_post_meta($id,'_abp_staff',$staff);
            update_post_meta($id,'_abp_date',$date); update_post_meta($id,'_abp_time',$time);
        }
        wp_safe_redirect(add_query_arg('abp_success','1',wp_get_referer()?:home_url('/'))); exit;
    }

    public function shortcode() {
        $s=$this->settings();
        $services=$this->parse_services($s['services']);
        $staff=$this->parse_staff($s['staff']);
        $slots=$this->slots($s);
        ob_start(); ?>
        <div class="abp-booking" style="--abp-accent:<?php echo esc_attr($s['accent']); ?>">
          <div class="abp-booking-card">
            <h3>رزرو نوبت</h3>
            <?php if(isset($_GET['abp_success'])):?><div class="abp-success"><?php echo esc_html($s['success_message']); ?></div><?php endif; ?>
            <?php if(isset($_GET['abp_error'])):?><div class="abp-error">اطلاعات نوبت معتبر نیست یا زمان انتخاب‌شده پر شده است.</div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="abp_submit_booking">
              <?php wp_nonce_field('abp_submit_booking','abp_nonce'); ?>
              <label>نام و نام خانوادگی<input type="text" name="name" required></label>
              <label>شماره موبایل<input type="tel" name="phone" required></label>
              <label>خدمت<select name="service" required><option value="">انتخاب کنید</option><?php foreach($services as $x): ?><option value="<?php echo esc_attr($x['name']); ?>"><?php echo esc_html($x['name'].($x['price']?' — '.$x['price']:'')); ?></option><?php endforeach; ?></select></label>
              <label>پرسنل<select name="staff" required><option value="">انتخاب کنید</option><?php foreach($staff as $x): ?><option value="<?php echo esc_attr($x); ?>"><?php echo esc_html($x); ?></option><?php endforeach; ?></select></label>
              <label>تاریخ<input type="date" name="date" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required></label>
              <label>ساعت<select name="time" required><option value="">انتخاب کنید</option><?php foreach($slots as $x): ?><option value="<?php echo esc_attr($x); ?>"><?php echo esc_html($x); ?></option><?php endforeach; ?></select></label>
              <button type="submit">ثبت نوبت</button>
            </form>
          </div>
        </div>
        <?php return ob_get_clean();
    }

}

new ABP_Plugin();
