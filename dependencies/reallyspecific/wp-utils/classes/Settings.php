<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils;

use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Assets\url as assets_url;
use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Assets\version as assets_version;
use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Text\array_to_attr_string;
use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Text\parsedown_line;
use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Environment\add_global_var;
use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Environment\get_global_var_footer_script;
class Settings
{
    private array $settings = [];
    private ?string $hook = null;
    private array $sections = [];
    private ?string $slug = null;
    private bool $multisite = \false;
    private ?int $post_id = null;
    private ?MultiArray $cache = null;
    /**
     * Constructor for the class.
     *
     * @param Plugin $plugin The plugin object.
     * @param string $menu_title The title of the menu.
     * @param array $props Additional properties for the settings.
     */
    public function __construct(array $props = [])
    {
        $props = wp_parse_args($props, ['slug' => null, 'capability' => 'manage_options', 'option_name' => null, 'post_id' => null, 'parent' => \false]);
        $this->slug = sanitize_title($props['slug']);
        unset($props['slug']);
        $this->settings = $props;
        if (!isset($props['option_name'])) {
            $this->settings['option_name'] = $this->slug;
        }
        if (is_multisite() && $props['capability'] === 'manage_network_options') {
            add_action('network_admin_menu', [$this, 'install']);
            $this->multisite = \true;
        } else {
            add_action('admin_menu', [$this, 'install']);
        }
        // todo: need another option for Post IDs.
        static $registered = \false;
        if (!$registered) {
            add_filter('rs_util_settings_sanitize_field_value', [$this, 'sanitize_textarea_field'], 10, 2);
            add_filter('rs_util_settings_sanitize_field_value', [$this, 'sanitize_sortable_field'], 10, 2);
            $registered = \true;
        }
    }
    /**
     * This is called during init to prevent i18n doing_it_wrong issues with translated strings on the labels.
     * @param mixed $props
     * @return void
     */
    public function setup($props)
    {
        $settings = wp_parse_args($props, ['menu_title' => ucwords($this->slug), 'page_title' => ucwords($this->slug)]);
        $this->settings = [...$this->settings, ...$settings];
        if (!has_action('admin_enqueue_scripts', [$this::class, 'enqueue_admin_scripts'])) {
            add_action('admin_enqueue_scripts', [$this::class, 'enqueue_admin_scripts']);
        }
        if (!has_filter('rs_util_settings_sanitize_field_value', [$this::class, 'sanitize_textarea_field'])) {
            add_filter('rs_util_settings_sanitize_field_value', [$this::class, 'sanitize_textarea_field'], 10, 2);
        }
    }
    public static function enqueue_admin_scripts()
    {
        //add_global_var( 'rs_util_settings.svg_iconset', assets_url( 'svg-iconset.svg' ) );
        wp_register_style('rs-util-settings-page', assets_url('rs-settings-page.css'), [], assets_version());
        wp_register_script('rs-util-settings-page', assets_url('rs-settings-page.js'), [], assets_version());
        wp_add_inline_script('rs-util-settings-page', 'rsUtil_SettingsPage.install();', 'after');
        //add_action( 'admin_footer', [ __CLASS__, 'render_global_settings' ] );
        do_action('rs_util_settings_enqueue_admin_scripts');
    }
    public static function render_global_settings()
    {
        echo get_global_var_footer_script('rs_util_settings', 'rsUtil_settingsPageENV');
    }
    public function hide_notices_from_other_plugins()
    {
        $screen = get_current_screen();
        if ($screen->id === $this->hook) {
            remove_all_actions('user_admin_notices');
            remove_all_actions('admin_notices');
        }
    }
    /**
     * Installs the menu page or submenu page based on the settings.
     *
     * @return void
     */
    public function install()
    {
        if ($this->settings['parent'] === \false) {
            $this->hook = add_menu_page($this->settings['page_title'], $this->settings['menu_title'], $this->settings['capability'], $this->slug, [$this, 'render'], $this->settings['icon_url'] ?? null, $this->settings['position'] ?? null);
        } else {
            $this->hook = add_submenu_page($this->settings['parent'], $this->settings['page_title'], $this->settings['menu_title'], $this->settings['capability'], $this->slug, [$this, 'render'], $this->settings['position'] ?? null);
        }
        add_action($this->hook, [$this, 'save_form'], -1);
        add_action('in_admin_header', [$this, 'hide_notices_from_other_plugins'], 99);
    }
    /**
     * Adds a section to the sections array with the given ID and properties.
     *
     * @param string $id The ID of the section.
     * @param array $props The properties of the section. Default is an empty array.
     * @return void
     */
    public function add_section(string $id = 'default', array $props = [])
    {
        if (!empty($props['fields'])) {
            $fields = $props['fields'];
            unset($props['fields']);
        }
        $this->sections[$id] = wp_parse_args($props, ['id' => $id, 'title' => null, 'description' => null, 'order' => (count($this->sections) + 1) * 10, 'fields' => []]);
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $this->add_field($field, $id);
            }
        }
    }
    /**
     * Sets the title for a section in the settings object.
     *
     * @param string $id The ID of the section.
     * @param string $title The title to set for the section.
     * @return void
     */
    public function set_section_title(string $id, string $title)
    {
        if (!isset($this->sections[$id])) {
            $this->add_section($id);
        }
        $this->sections[$id]['title'] = $title;
    }
    /**
     * Sets the description for a section in the settings object.
     *
     * @param string $id The ID of the section.
     * @param string $title The description to set for the section.
     * @return void
     */
    public function set_section_description(string $id, string $title)
    {
        if (!isset($this->sections[$id])) {
            $this->add_section($id);
        }
        $this->sections[$id]['description'] = $title;
    }
    /**
     * Adds a field to a section in the settings.
     *
     * @param array $props The properties of the field.
     * @param string|null $section_id The ID of the section to add the field to. If not provided, the field will be added to the default section.
     * @throws \Exception If the field does not have a name.
     * @return void
     */
    public function add_field(array $props, ?string $section_id = null)
    {
        if ($section_id === null) {
            $section_id = 'default';
            if (!isset($this->sections['default'])) {
                $this->add_section('default', ['order' => 0]);
            }
        }
        if (!isset($this->sections[$section_id])) {
            $this->add_section($section_id);
        }
        if (!isset($props['name'])) {
            throw new \Exception('Cannot attach fields without a name.');
        }
        $this->sections[$section_id]['fields'][] = wp_parse_args($props, ['type' => 'text', 'order' => (count($this->sections[$section_id]['fields']) + 1) * 10, 'label' => $props['name'], 'id' => $section_id . '__' . sanitize_title($props['name']), 'default' => null, 'placeholder' => null, 'description' => null]);
    }
    public function render()
    {
        wp_enqueue_style('rs-util-settings-page');
        ?>
		<div class="wrap rs-util-settings-page wp-ui">
			<div class="rs-util-settings-page-title wp-ui-primary">
				<?php 
        do_action($this->slug . '_rs_util_settings_render_form_title_afterstart', $this);
        ?>	
				<h1 class="wp-heading-inline"><?php 
        echo $this->settings['page_title'];
        ?></h1>
				<?php 
        do_action($this->slug . '_rs_util_settings_render_form_title_beforeend', $this);
        ?>
				<?php 
        if (count($this->sections) > 1) {
            ?>
					<div class="rs-util-settings-page__tabs">
						<?php 
            $i = 0;
            foreach ($this->sections as $section) {
                ?>
							<button type="button" aria-expanded="<?php 
                echo !$i ? 'true' : 'false';
                ?>" class="rs-util-settings-page__tab-toggle button-primary" data-section="<?php 
                echo $section['id'];
                ?>"><?php 
                echo $section['tab_label'] ?? $section['title'] ?? 'General';
                ?></button>
						<?php 
                $i++;
            }
            ?>
					</div>
				<?php 
        }
        ?>
			</div>
			
			<?php 
        do_action($this->slug . '_rs_util_settings_render_form_beforestart', $this);
        ?>
			<?php 
        do_action('rs_util_settings_render_form_beforestart', $this);
        ?>
			<form class="rs-util-settings-form" method="post" action="<?php 
        echo $this->settings['form_url'] ?? $_SERVER['REQUEST_URI'];
        ?>">
				<?php 
        do_action($this->slug . '_rs_util_settings_render_form_afterstart', $this);
        ?>
				<?php 
        do_action('rs_util_settings_render_form_afterstart', $this);
        ?>
				<?php 
        wp_nonce_field($this->slug);
        ?>
				<?php 
        $i = 0;
        foreach ($this->sections as $section) {
            ?>
					<div aria-hidden="<?php 
            echo !$i ? 'false' : 'true';
            ?>" class="rs-util-settings-section" data-section="<?php 
            echo $section['id'];
            ?>">
						<?php 
            if (!empty($section['title'])) {
                ?>
						<h2 class="rs-util-settings-section__title"><?php 
                echo $section['title'];
                ?>
							<?php 
                if (!empty($section['description'])) {
                    ?>
								<small class="rs-util-settings-section__description"><?php 
                    echo parsedown_line($section['description'], 'description', 'rs-util-settings');
                    ?></small>
							<?php 
                }
                ?>
						</h2>
						<?php 
            }
            ?>

						<?php 
            if (isset($section['fields'])) {
                ?>
						<?php 
                do_action($this->slug . '_rs_util_settings_render_section_beforestart', $section, $this);
                ?>
						<?php 
                do_action('rs_util_settings_render_section_beforestart', $section, $this);
                ?>
						<div class="rs-util-settings-form-table">
							<?php 
                $current_group = null;
                $current_subgroup = null;
                foreach ($section['fields'] as $field) {
                    if (($field['group'] ?? null) !== $current_group) {
                        if ($current_group) {
                            if ($current_subgroup) {
                                printf('</div></div>');
                            }
                            printf('</div></div>');
                        }
                        if ($field['group'] ?? null) {
                            $current_group = $field['group'];
                            printf('<div class="rs-util-settings-field-group"%s>' . '<div class="rs-util-settings-field-group__label"><span>%s</span>%s</div>' . '<div class="rs-util-settings-field-group__content">', isset($field['ordering']) && empty($field['subgroup']) ? ' data-ordered="' . $field['ordering'] . '"' : '', $current_group, isset($field['group_desc']) ? '<p class="rs-util-settings-field-group__description">' . parsedown_line($field['group_desc'], 'description', 'rs-util-settings') . '</p>' : '');
                        }
                        $current_group = $field['group'] ?? null;
                        $current_subgroup = null;
                    }
                    $field_name = $field['attrs']['name'] ?? $field['name'] ?? '';
                    do_action($this->slug . '_rs_util_settings_render_field_row_beforestart', $field, $section, $this);
                    do_action('rs_util_settings_render_field_row_beforestart', $field, $section, $this);
                    if (($field['subgroup'] ?? null) !== $current_subgroup) {
                        if ($current_subgroup) {
                            printf('</div></div>');
                        }
                        if ($field['subgroup']) {
                            $current_subgroup = $field['subgroup'];
                            printf('<div class="rs-util-settings-field-row"%s%s>' . '<div class="rs-util-settings-field-row__label"><span>%s</span></div>' . '<div class="rs-util-settings-field-row__group">', isset($field['subgroup_toggled_by']) ? ' data-toggled-by="' . sanitize_title($field['subgroup_toggled_by']) . '"' : '', isset($field['ordering']) ? ' data-ordered="' . $field['ordering'] . '"' : '', $current_subgroup);
                        }
                        $current_subgroup = $field['subgroup'] ?? null;
                    }
                    $this->render_field_row($field, $this->get($field_name) ?? null);
                    do_action('rs_util_settings_render_field_row_afterend', $field, $section, $this);
                    do_action($this->slug . '_rs_util_settings_render_field_row_afterend', $field, $section, $this);
                }
                if ($current_subgroup) {
                    printf('</div></div>');
                }
                if ($current_group) {
                    printf('</div></div>');
                }
                ?>
						</div>
						<?php 
                do_action($this->slug . '_rs_util_settings_render_section_afterend', $section, $this);
                ?>
						<?php 
                do_action('rs_util_settings_render_section_afterend', $section, $this);
                ?>
						<?php 
            }
            ?>
					</div>
				<?php 
            $i++;
        }
        ?>
				<?php 
        do_action('rs_util_settings_render_form_beforeend', $this);
        ?>
				<?php 
        do_action($this->slug . '_rs_util_settings_render_form_beforeend', $this);
        ?>
			</form>
			<?php 
        do_action('rs_util_settings_render_form_afterend', $this);
        ?>
			<?php 
        do_action($this->slug . '_rs_util_settings_render_form_afterend', $this);
        ?>

			<div class="rs-util-settings-page-actions wp-ui-primary">
				<button disabled type="button" data-action="save-rs-util-page" class="button button-primary button-submit rs-util-settings-page__submit">
					<span><?php 
        _e('Save Changes', 'rs-util-settings');
        ?></span>
				</button>
			</div>
		</div>
		<?php 
        wp_enqueue_script('rs-util-settings-page');
    }
    public function render_field_row(array $field, $value = null, $echo = \true)
    {
        $label = apply_filters($this->slug . '_rs_util_settings_render_field_row_label', $field['label'] ?? '', $field, $value, $this);
        $label = apply_filters('rs_util_settings_render_field_row_label', $label, $field, $value, $this);
        $description = apply_filters($this->slug . '_rs_util_settings_render_field_row_description', $field['description'] ?? null, $field, $value, $this);
        $description = apply_filters('rs_util_settings_render_field_row_description', $description, $field, $value, $this);
        $attrs = [];
        if (isset($field['toggled_by'])) {
            $attrs['data-toggled-by'] = sanitize_title($field['toggled_by']);
            $attrs['aria-hidden'] = 'true';
        }
        $row_class = 'rs-util-settings-field-row';
        if ($field['subgroup'] ?? null) {
            $row_class = 'rs-util-settings-field-subgroup';
        }
        if ($field['type'] ?? null) {
            $row_class .= " {$row_class}--{$field['type']}";
        }
        if ($field['style'] ?? null) {
            $row_class .= ' is-style-' . $field['style'];
        }
        ob_start();
        if ($field['type'] === 'hidden') {
            $this->render_field($field, $value);
        } else {
            ?>

		<div class="<?php 
            echo esc_attr($row_class);
            ?>" <?php 
            echo array_to_attr_string($attrs);
            ?>>
		<?php 
            if (!empty($label)) {
                ?>
				<div class="rs-util-settings-field-row__label">
					<label for="<?php 
                echo esc_attr($field['id']);
                ?>"><?php 
                echo $label;
                ?></label>
				</div>
			<?php 
            }
            ?>
			<div class="rs-util-settings-field-value rs-util-settings-field-value--<?php 
            echo $field['type'];
            ?>">
				<?php 
            $this->render_field($field, $value);
            ?>
				<?php 
            if (!empty($description)) {
                ?>
				<p class="rs-util-settings-field__description">
					<?php 
                echo parsedown_line($description, 'description', 'rs-util-settings');
                ?>
				</p>
				<?php 
            }
            ?>
			</div>
		</div>

		<?php 
        }
        $rendered = ob_get_clean();
        $rendered = apply_filters($this->slug . '_rs_util_settings_render_field_row', $rendered, $field, $value, $this);
        $rendered = apply_filters('rs_util_settings_render_field_row', $rendered, $field, $value, $this);
        if ($echo) {
            echo $rendered;
        }
        return $rendered;
    }
    private function parse_field_name(string $name, bool $multiple = \false)
    {
        $name_parts = explode('.', $name);
        $new_name = sanitize_title(array_shift($name_parts));
        while (count($name_parts) > 0) {
            $new_name .= '[' . sanitize_title(array_shift($name_parts)) . ']';
        }
        if (!empty($multiple)) {
            $new_name .= '[]';
        }
        return $new_name;
    }
    public function render_field(array $field, $value = null, $echo = \true)
    {
        $field_name = $field['name'];
        $tag = match ($field['type']) {
            'options' => 'multicheck',
            'select' => 'select',
            'sortable' => 'select',
            'textarea' => 'textarea',
            default => 'input',
        };
        $attrs = wp_parse_args($field['attrs'] ?? [], ['id' => $field['id'], 'name' => null, 'required' => filter_var($field['required'] ?? null, \FILTER_VALIDATE_BOOLEAN) ? 'required' : null, 'class' => $field['class'] ?? [], 'multiple' => filter_var($field['multiple'] ?? null, \FILTER_VALIDATE_BOOLEAN) ? 'multiple' : null]);
        $attrs['name'] = $attrs['name'] ?? $this->parse_field_name($field_name, !empty($attrs['multiple']));
        $attrs['class'] = is_array($attrs['class']) ? $attrs['class'] : [$attrs['class']];
        $attrs['class'][] = match ($field['type']) {
            'input' => 'regular-text',
            'textarea' => 'large-text',
            default => '',
        };
        $attrs['class'][] = 'rs-util-settings-field';
        $attrs['class'][] = 'rs-util-settings-field--' . $field['type'];
        $attrs['class'] = trim(implode(' ', array_unique($attrs['class'])));
        if (isset($field['ordering'])) {
            $attrs['data-ordering-field'] = \true;
            $attrs['id'] = '';
        }
        if (!empty($field['toggles_group'])) {
            $attrs['data-toggles-group'] = is_string($field['toggles_group']) ? $field['toggles_group'] : 'self';
        }
        $value = $value ?? $field['default'] ?? (empty($attrs['multiple']) ? '' : []);
        switch ($tag) {
            case 'input':
                $attrs['size'] = $field['size'] ?? null;
                $attrs['type'] = $field['type'] ?? 'text';
                $attrs['placeholder'] = $field['placeholder'] ?? null;
                if ($field['type'] === 'checkbox') {
                    $checked = filter_var($value ?? $field['default'] ?? null, \FILTER_VALIDATE_BOOLEAN);
                    $attrs['checked'] = $checked ? 'checked' : null;
                    $value = null;
                } else {
                    $attrs['value'] = $value ?? $field['default'] ?? null;
                }
                $render_template = '<input %1$s>';
                if ($attrs['type'] === 'checkbox' || $attrs['type'] === 'radio') {
                    $render_template .= '<span class="rs-util-settings-field-icon__toggle"></span>';
                }
                if (isset($field['value_label'])) {
                    $render_template .= '<label for="' . $attrs['id'] . '">' . $field['value_label'] . '</label>';
                }
                break;
            case 'textarea':
                $attrs['rows'] = $field['rows'] ?? null;
                $attrs['cols'] = $field['cols'] ?? null;
                $render_template = '<textarea %1$s>' . esc_html($value) . '</textarea>';
                break;
            case 'select':
                $attrs['placeholder'] = $field['placeholder'] ?? null;
                $render_template = [$this, 'render_select'];
                break;
            case 'multicheck':
                $render_template = [$this, 'render_multicheck'];
                break;
        }
        if (is_callable($render_template)) {
            $rendered = call_user_func($render_template, $field, $value, $attrs);
        } else {
            $rendered = sprintf($render_template ?? '', array_to_attr_string($attrs));
        }
        $rendered = apply_filters($this->slug . '_rs_util_settings_render_field_' . $field_name, $rendered, $field, $value, $this);
        $rendered = apply_filters($this->slug . '_rs_util_settings_render_field', $rendered, $field, $value, $this);
        $rendered = apply_filters('rs_util_settings_render_field', $rendered, $field, $value, $this);
        if (!empty($rendered) && $echo) {
            echo $rendered;
        }
        return $rendered ?? '';
    }
    public function render_select(array $field, $value, array $attrs)
    {
        $buffer = '';
        if (is_callable($field['options'])) {
            $field['options'] = call_user_func($field['options']);
        }
        if ($field['type'] === 'sortable') {
            $attrs['data-use-tom-select'] = 'true';
            $attrs['data-action'] = 'add-item';
            $attrs['multiple'] = \false;
            if (str_ends_with($attrs['name'], '[]')) {
                $attrs['name'] = substr($attrs['name'], 0, -2);
            }
            $hidden_attrs = ['name' => $attrs['name'], 'type' => 'hidden', 'value' => $value ? json_encode($value) : ''];
            unset($attrs['name']);
            unset($attrs['value']);
            $hidden_attrs = apply_filters('rs_util_settings_render_sortable_hidden_attrs', $hidden_attrs, $field, $value, $this);
            $buffer .= '<input type="hidden" ' . array_to_attr_string($hidden_attrs) . '>';
            $buffer .= '<div class="rs-util-settings-sortable-list">';
            $values = apply_filters('rs_util_settings_render_sortable_values', $value ?: [], $field, $this);
            foreach ($values as $item) {
                if (is_array($item)) {
                    $item_id = $item['value'];
                    $item = $item['label'];
                } else {
                    $item_id = $item;
                }
                $buffer .= sprintf('<div class="rs-util-settings-sortable-list-item" data-value="%s">' . '<span class="rs-util-settings-draggable-handle"></span>' . '%s' . '<button type="button" class="rs-util-settings-trash-btn" data-action="remove-item">Remove Sticky</button>' . '</div>', esc_attr($item_id), esc_html($item));
            }
            $buffer .= '</div>';
            $value = '';
        }
        if (isset($field['enable_tom'])) {
            $attrs['data-use-tom-select'] = 'true';
        }
        if (isset($field['data'])) {
            $value = $field['data_value'] ?? 'id';
            $label = $field['data_label'] ?? 'title';
            $attrs['data-source'] = json_encode(['url' => $field['data'], 'value' => $value, 'label' => $label]);
        }
        $buffer .= '<select ' . array_to_attr_string($attrs) . '>';
        foreach ($field['options'] as $key => $option) {
            if (isset($option['group'])) {
                $buffer .= sprintf('<optgroup label="%s">', esc_attr($option['group']));
                $suboptions = $option['options'] ?? [];
            } else {
                $suboptions = [$key => $option];
            }
            foreach ($suboptions as $subkey => $suboption) {
                $subattrs = [];
                $label = '';
                if (is_string($suboption)) {
                    $label = $suboption;
                } else {
                    $label = $suboption['label'] ?? $subkey;
                    if (isset($suboption['data'])) {
                        foreach ($suboption['data'] as $data_key => $data_value) {
                            $subattrs["data-{$data_key}"] = $data_value;
                        }
                    }
                }
                if ($subkey === $value || is_array($value) && in_array($subkey, $value)) {
                    $subattrs['selected'] = 'selected';
                }
                $buffer .= sprintf('<option value="%s" %s>%s</option>', esc_attr($subkey), array_to_attr_string($subattrs), esc_html($label));
            }
            if (isset($option['group'])) {
                $buffer .= '</optgroup>';
            }
        }
        $buffer .= '</select>';
        return $buffer;
    }
    public function render_multicheck(array $field, $value, array $attrs)
    {
        $buffer = '';
        $buffer .= '<div class="rs-util-settings-field rs-util-settings-field--multicheck">';
        if (is_callable($field['options'])) {
            $field['options'] = call_user_func($field['options']);
        }
        foreach ($field['options'] as $key => $option) {
            if (isset($option['group'])) {
                $buffer .= sprintf('<fieldset class="rs-util-settings-field__fieldset"><legend><strong>%s</strong></legend>', esc_attr($option['group']));
                $suboptions = $option['options'] ?? [];
            } else {
                $suboptions = [$key => $option];
            }
            foreach ($suboptions as $subkey => $suboption) {
                $subattrs = ['class' => ''];
                $label = '';
                if (is_string($suboption)) {
                    $label = $suboption;
                } else {
                    $label = $suboption['label'] ?? $subkey;
                    if (isset($suboption['data'])) {
                        foreach ($suboption['data'] as $data_key => $data_value) {
                            $subattrs["data-{$data_key}"] = $data_value;
                        }
                    }
                    if (isset($suboption['classes'])) {
                        $subattrs['class'] = implode(' ', $suboption['classes']);
                    }
                }
                $class = trim($attrs['class'] . ' ' . $subattrs['class']);
                $class = str_replace('rs-util-settings-field--multicheck', 'rs-util-settings-field--input', $class);
                $subattrs['class'] = $class;
                $subattrs['name'] = empty($attrs['multiple']) ? $attrs['name'] : str_replace('[]', "[{$subkey}]", $attrs['name']);
                if ($subkey === $value || is_array($value) && in_array($subkey, $value)) {
                    $subattrs['checked'] = 'checked';
                }
                $buffer .= sprintf('<div class="rs-util-settings-field__option">' . '<span class="rs-util-settings-field-icon__toggle"></span>' . '<input type="%1$s" value="%2$s" %3$s><label for="%2$s">%4$s</label>' . '</div>', empty($attrs['multiple']) ? 'radio' : 'checkbox', esc_attr($subkey), array_to_attr_string($subattrs), esc_html($label));
            }
            if (isset($option['group'])) {
                $buffer .= '</fieldset>';
            }
        }
        $buffer .= '</div>';
        return $buffer;
    }
    private function get_request_value(string $key, array $request)
    {
        $keys = explode('.', $key);
        foreach ($keys as $key) {
            if (!isset($request[$key])) {
                return null;
            }
            $request = $request[$key];
        }
        return $request;
    }
    public function save_form()
    {
        if (!current_user_can($this->settings['capability'])) {
            return;
        }
        if (($_GET['page'] ?? '') !== $this->slug) {
            return;
        }
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], $this->slug)) {
            return;
        }
        $this->cache = new MultiArray([]);
        foreach ($this->sections as $section) {
            foreach ($section['fields'] as $field) {
                $field_name = $field['attrs']['name'] ?? $field['name'];
                if (empty($field_name)) {
                    continue;
                }
                $field_value = $this->get_request_value($field_name, $_POST);
                $sanitization_function = apply_filters('rs_util_settings_sanitize_field_value', $field['sanitization_callback'] ?? null, $field);
                if (empty($sanitization_function)) {
                    $sanitization_function = 'sanitize_text_field';
                }
                if (is_array($field_value)) {
                    $new_value = [];
                    foreach ($field_value as $value) {
                        $new_value[] = call_user_func($sanitization_function, $value, $field);
                    }
                } else {
                    $new_value = call_user_func($sanitization_function, $field_value, $field);
                }
                $this->update($field_name, $new_value, \false);
            }
        }
        $this->save();
        do_action('rs_util_settings_saved', $this);
    }
    public function get(?string $key = null, $nocache = \false)
    {
        if (is_null($this->cache) || $nocache) {
            $this->load();
        }
        if (empty($key)) {
            return $this->cache->to_array();
        }
        $value = $this->cache[$key];
        if ($value instanceof MultiArray) {
            return $value->to_array();
        }
        return $value;
    }
    public function __get($key)
    {
        return $this->get($key);
    }
    private function load()
    {
        if ($this->multisite) {
            $cache = get_site_option($this->settings['option_name'], []) ?: [];
        } else {
            $cache = get_option($this->settings['option_name'], []) ?: [];
        }
        $this->cache = new MultiArray($cache);
    }
    public function update($key, $value, $save = \true)
    {
        if (!isset($this->cache)) {
            $this->load();
        }
        $this->cache[$key] = $value;
        if ($save) {
            $this->save();
        }
        return $this->cache->to_array();
    }
    public function save()
    {
        if ($this->post_id) {
            update_post_meta($this->post_id, $this->settings['option_name'], $this->cache->to_array());
        } elseif ($this->multisite) {
            update_site_option($this->settings['option_name'], $this->cache->to_array());
        } else {
            update_option($this->settings['option_name'], $this->cache->to_array());
        }
    }
    public function add_action($hook, $callback, $priority = 10, $args = 0)
    {
        add_action($this->slug . '_' . $hook, $callback, $priority, $args);
    }
    public function add_filter($hook, $callback, $priority = 10, $args = 1)
    {
        add_filter($this->slug . '_' . $hook, $callback, $priority, $args);
    }
    public static function sanitize_textarea_field($callback_function, $field = [])
    {
        if (($field['type'] ?? null) === 'textarea') {
            return 'sanitize_textarea_field';
        }
        return $callback_function;
    }
    public static function sanitize_sortable_field($callback_function, $field = [])
    {
        if (($field['type'] ?? null) === 'sortable') {
            return function ($value) {
                if (empty($value)) {
                    return null;
                }
                if (is_array($value)) {
                    return $value;
                }
                $json = json_decode(stripslashes($value), \true);
                return $json ?: null;
            };
        }
        return $callback_function;
    }
}
