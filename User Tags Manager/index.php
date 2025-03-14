<?php
/*
Plugin Name: User Tags Manager
Description: Adds custom taxonomy "User Tags" for categorizing users and enables filtering in admin.
Version:     1.0
Author:      Arjun Mishra
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class User_Tags_Manager {

    public function __construct() {
        // Hooks for plugin activation and deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Register custom taxonomy for users
        add_action('init', [$this, 'register_user_tags_taxonomy']);
        
        // Add User Tags menu under Users section in WP Admin
        add_action('admin_menu', [$this, 'add_user_tags_admin_menu']);
        
        // Display User Tags field on user profile and registration forms
        add_action('show_user_profile', [$this, 'add_user_tags_field']);
        add_action('edit_user_profile', [$this, 'add_user_tags_field']);
        add_action('user_new_form', [$this, 'add_user_tags_field']); 
        
        // Save User Tags when profile is updated or user is registered
        add_action('personal_options_update', [$this, 'save_user_tags']);
        add_action('edit_user_profile_update', [$this, 'save_user_tags']);
        add_action('user_register', [$this, 'save_user_tags']); 
        
        // Add User Tags column to Users list table in admin
        add_filter('manage_users_columns', [$this, 'add_user_tags_column']);
        add_action('manage_users_custom_column', [$this, 'display_user_tags_column'], 10, 3);
        
        // Add dropdown filter in Users admin panel
        add_action('restrict_manage_users', [$this, 'add_user_tags_filter_dropdown']);
        
        // Filter users based on selected tag in admin
        add_action('pre_get_users', [$this, 'filter_users_by_tags']);
        
        // Enqueue admin scripts for Select2 dropdown enhancement
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function activate() {
        // Register taxonomy on activation and flush rewrite rules
        $this->register_user_tags_taxonomy();
        flush_rewrite_rules();
    }

    public function deactivate() {
        // Flush rewrite rules on deactivation
        flush_rewrite_rules();
    }

    public function register_user_tags_taxonomy() {
        // Define and register the "User Tags" taxonomy
        $args = [
            'public'       => false,
            'show_ui'      => true,
            'hierarchical' => false,
            'label'        => 'User Tags',
            'query_var'    => true,
            'rewrite'      => false,
        ];
        register_taxonomy('user_tags', 'user', $args);
    }

    public function add_user_tags_admin_menu() {
        // Add "User Tags" management under Users menu
        add_users_page('User Tags', 'User Tags', 'manage_options', 'edit-tags.php?taxonomy=user_tags');
    }

    public function add_user_tags_field($user) {
        // Display "User Tags" selection field in user profile form
        $user_tags = is_object($user) ? wp_get_object_terms($user->ID, 'user_tags', ['fields' => 'ids']) : [];
        ?>
        <h3>User Tags</h3>
        <table class="form-table">
            <tr>
                <th><label for="user_tags">User Tags</label></th>
                <td>
                    <select name="user_tags[]" id="user_tags" multiple="multiple" style="width: 400px;">
                        <?php
                        $terms = get_terms(['taxonomy' => 'user_tags', 'hide_empty' => false]);
                        foreach ($terms as $term) {
                            $selected = in_array($term->term_id, $user_tags) ? 'selected' : '';
                            echo "<option value='{$term->term_id}' $selected>{$term->name}</option>";
                        }
                        ?>
                    </select>
                    <p class="description">Select user tags.</p>
                </td>
            </tr>
        </table>
        <script>
            jQuery(document).ready(function ($) {
                $('#user_tags').select2({ placeholder: "Select User Tags", allowClear: true });
            });
        </script>
        <?php
    }

    public function save_user_tags($user_id) {
        // Save selected user tags when profile is updated
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        $user_tags = isset($_POST['user_tags']) ? array_map('intval', $_POST['user_tags']) : [];
        wp_set_object_terms($user_id, $user_tags, 'user_tags', false);
    }

    public function add_user_tags_column($columns) {
        // Add "User Tags" column in Users admin table
        $columns['user_tags'] = 'User Tags';
        return $columns;
    }

    public function display_user_tags_column($value, $column_name, $user_id) {
        // Display user tags in Users admin table column
        if ($column_name == 'user_tags') {
            $tags = wp_get_object_terms($user_id, 'user_tags', ['fields' => 'names']);
            return $tags ? implode(', ', $tags) : '—';
        }
        return $value;
    }

    public function add_user_tags_filter_dropdown() {
        // Add dropdown filter in Users admin page
        $selected_tag = isset($_GET['filter_user_tag']) ? $_GET['filter_user_tag'] : '';
        $terms = get_terms(['taxonomy' => 'user_tags', 'hide_empty' => false]);

        echo '<form method="GET">';
        echo '<select name="filter_user_tag">';
        echo '<option value="">Filter by User Tags</option>';
        foreach ($terms as $term) {
            $selected = selected($selected_tag, $term->term_id, false);
            echo "<option value='{$term->term_id}' {$selected}>{$term->name}</option>";
        }
        echo '</select>';
        echo '<input type="submit" class="button button-primary" value="Filter">';
        echo '</form>';
    }

    public function filter_users_by_tags($query) {
        // Modify WP_User_Query to filter users based on selected tag
        if (!is_admin() || !($query instanceof WP_User_Query) || empty($_GET['filter_user_tag'])) {
            return;
        }
        $tag_id = intval($_GET['filter_user_tag']); 
        $users_with_tag = get_objects_in_term($tag_id, 'user_tags');

        if (!empty($users_with_tag)) {
            $query->set('include', $users_with_tag);
        } else {
            $query->set('include', [0]);
        }
    }

    public function enqueue_admin_scripts($hook) {
        // Load Select2 script and style on relevant admin pages
        if (in_array($hook, ['users.php', 'profile.php', 'user-edit.php', 'user-new.php'])) {
            wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], null, true);
            wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        }
    }
}

new User_Tags_Manager();