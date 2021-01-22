<?php
/**
 * Plugin Name: WP Admin Boost
 * Description: 使用jsdelivr加速WordPress的后台核心小文件与插件小文件，大幅提高后台访问速度。
 * Author: 潘羿
 * Author URI:https://www.idleleo.com/
 * Version: 1.0.2
 * Network: True
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

(new WP_ADMIN_BOOST)->init();

class WP_ADMIN_BOOST
{
    private $page_url;

    public function __construct()
    {
        $this->page_url = network_admin_url(is_multisite() ? 'settings.php?page=wp-admin-boost' : 'options-general.php?page=wp-admin-boost');
    }

    public function init()
    {
        if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            add_filter(sprintf('%splugin_action_links_%s', is_multisite() ? 'network_admin_' : '', plugin_basename(__FILE__)), function ($links) {
                return array_merge(
                    [sprintf('<a href="%s">%s</a>', $this->page_url, '设置')],
                    $links
                );
            });

            update_option("wpab_admin", get_option('wpab_admin') ?: '2');
            update_option("wpab_admin_plugin", get_option('wpab_admin_plugin') ?: '2');
            update_option("wpab_block_activate_plugin", get_option('wpab_block_activate_plugin') ?: '');
            register_deactivation_hook(__FILE__, function () {
                delete_option("wpab_admin");
                delete_option("wpab_admin_plugin");
                delete_option("wpab_block_activate_plugin");
            });

            add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', function () {
                add_submenu_page(
                    is_multisite() ? 'settings.php' : 'options-general.php',
                    'WP Admin Boost',
                    'WP Admin Boost',
                    is_multisite() ? 'manage_network_options' : 'manage_options',
                    'wp-admin-boost',
                    [$this, 'options_page_html']
                );
            });

            if (get_option('wpab_admin') != 2 && !stristr($GLOBALS['wp_version'], 'alpha') && !stristr($GLOBALS['wp_version'], 'beta')) {
                add_action('init', function () {
                    ob_start(function ($buffer) {
                        $buffer = preg_replace('~' . home_url('/') . '(wp-admin|wp-includes)/(css|js)/~', sprintf('//cdn.jsdelivr.net/gh/WordPress/WordPress@%s/$1/$2/', $GLOBALS['wp_version']), $buffer);
                        return $buffer;
                    });
                });
            };

            if (get_option('wpab_admin_plugin') == 1) {
                add_action('init', 'panyi_admin_speedup');
                function panyi_admin_speedup()
                {
                    ob_start(function ($buffer) {
                        $apl = get_option('active_plugins');
                        $plugins = get_plugins();
                        if (get_option('wpab_block_activate_plugin')) {
                            $block_speedup = explode(",", get_option('wpab_block_activate_plugin'));
                        }
                        foreach ($apl as $p) {
                            if (isset($plugins[$p])) {
                                $path = preg_replace('~(.*)/(?:.*).php~', '$1', $p);
                                if (in_array($path, $block_speedup)) {
                                    continue;
                                }
                                $buffer = preg_replace('~' . home_url('/') . 'wp-content/plugins/' . $path . '/(.*).(css|js|woff|woff2|jpg|png|gif|svg|webp)~', sprintf('//cdn.jsdelivr.net/wp/plugins/' . $path . '/tags/%s/$1.$2', $plugins[$p]['Version']), $buffer);
                            }
                        }
                        return $buffer;
                    });
                }
            }
        }
        if (is_admin()) {
            add_action('admin_init', function () {
                register_setting('wpab', 'wpab_admin');
                register_setting('wpab', 'wpab_admin_plugin');
                register_setting('wpab', 'wpab_block_activate_plugin');

                add_settings_section(
                    'wpab_section_main',
                    '设置',
                    [$this, 'field_section_main'],
                    'wpab'
                );

                add_settings_field(
                    'wpab_field_select_admin',
                    '加速管理后台',
                    [$this, 'field_admin'],
                    'wpab',
                    'wpab_section_main'
                );

                add_settings_field(
                    'wpab_field_select_admin_plugin',
                    '加速后台插件',
                    [$this, 'field_admin_plugin'],
                    'wpab',
                    'wpab_section_main'
                );

                add_settings_field(
                    'wpab_field_select_block_plugin',
                    '禁用加速插件',
                    [$this, 'field_select_block_plugin'],
                    'wpab',
                    'wpab_section_main'
                );
            });
        }
    }
    
    public function field_section_main()
    {
    ?>
        <p class="description">使用jsdelivr提供的CDN加速WordPress后台，包括核心小文件与插件小文件</p>
    <?php
    }

    public function field_admin()
    {
        $this->field_cb('wpab_admin', '将WordPress核心所依赖的静态文件切换为公共资源，此选项极大的加快管理后台访问速度', true);
    }

    public function field_admin_plugin()
    {
        $this->field_cb('wpab_admin_plugin', '将所有激活的插件小文件切换为公共资源，进一步加快管理后台访问速度，<b>注意！请仔细检查是否兼容</b>', true);
    }

    public function field_select_block_plugin()
    {
        $ntap = explode(",", get_option('wpab_block_activate_plugin'));
        $apl = get_option('active_plugins');
        $plugins = get_plugins();
        ?>
        <p class="description">
            请选择需要<b>禁用</b>加速的插件：
        </p>
        <table>
            <thead>
                <tr>
                    <th>
                        插件名
                    </th>
                    <th>
                        版本号
                    </th>
                    <th>
                        禁用
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php
                foreach ($apl as $p) {
                    $path = preg_replace('~(.*)/(?:.*).php~', '$1', $p);
                    if (isset($plugins[$p])) {
                    ?>
                    <tr>
                        <td><?php echo $plugins[$p]['Name']; ?></td>
                        <td><?php echo $plugins[$p]['Version']; ?></td>
                        <td><label><input type="checkbox" value="<?php echo $path; ?>" name="block_activate_plugin[]" <?php if(in_array($path, $ntap)){echo'checked="checked"';} ?>></label></td>
                    </tr>
            <?php 
                }
            }
            ?>
            </tbody>
        </table>
        <?php
    }

    private function field_cb($option_name, $description, $is_global = false)
    {
        $option_value = get_option($option_name);
        ?>
        <label>
            <input type="radio" value="1" name="<?php echo $option_name; ?>" <?php checked($option_value, '1');?>><?php echo $is_global ? '启用&emsp;&emsp;' : '全局启用&emsp;&emsp;' ?>
        </label>
        <label>
            <input type="radio" value="2" name="<?php echo $option_name; ?>" <?php checked($option_value, '2');?>>禁用
        </label>
        <p class="description">
            <?php echo $description; ?>
        </p>
        <?php
    }

    public function options_page_html()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            update_option("wpab_admin", sanitize_text_field($_POST['wpab_admin']));
            update_option("wpab_admin_plugin", sanitize_text_field($_POST['wpab_admin_plugin']));
            update_option("wpab_block_activate_plugin", sanitize_text_field(implode(',', $_POST['block_activate_plugin'])));
            echo '<div class="notice notice-success settings-error is-dismissible"><p><strong>设置已保存</strong></p></div>';
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors('wpab_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="<?php echo $this->page_url; ?>" method="post">
                <?php
        settings_fields('wpab');
        do_settings_sections('wpab');
        submit_button('保存配置');
        ?>
            </form>
        </div>
        <p style="text-align:right;">
            欢迎访问<a href="https://www.idleleo.com/" target="_blank">无主界</a>！获取更多的WordPress技巧。<br/>
            感谢<a href="https://wp-china.org" target="_blank">WP中国本土化社区</a>的源代码。
        </p>
        <?php
    }
}
