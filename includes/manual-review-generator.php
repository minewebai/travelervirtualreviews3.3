<?php
function tvr_manual_review_generator_page() {
    $config = function_exists('tvr_get_config') ? tvr_get_config() : [];
    $languages = isset($config['languages']) ? $config['languages'] : ['en' => ['name' => 'English']];

    // Language selection
    $selected_lang = isset($_POST['manual_review_language'])
        ? sanitize_text_field($_POST['manual_review_language'])
        : (function_exists('icl_object_id') ? apply_filters('wpml_current_language', NULL) : 'en');

    echo '<div class="wrap"><h1>Manual Review Generator</h1>';

    // Language dropdown above review generation
    echo '<form method="post" id="manual-review-language-form" style="margin-bottom: 1em;">';
    echo '<label for="manual_review_language"><strong>Review Language</strong></label> ';
    echo '<select name="manual_review_language" id="manual_review_language" onchange="this.form.submit()">';
    foreach ($languages as $code => $lang) {
        echo '<option value="'.esc_attr($code).'" '.selected($selected_lang, $code, false).'>'.esc_html($lang['name']).' ('.$code.')</option>';
    }
    echo '</select>';
    echo '</form>';

    // Keep the selected language in all future forms
    $lang_hidden = '<input type="hidden" name="manual_review_language" value="'.esc_attr($selected_lang).'">';

    if (!empty($_POST['generate_count'])) {
        $count = intval($_POST['generate_count']);
        echo '<h2>Generated Reviews</h2>';
        echo '<form method="post"><table class="widefat fixed striped">';
        echo '<thead><tr><th>Name</th><th>Email</th><th>Rating</th><th>Review Title</th><th>Review Content</th><th>Date</th></tr></thead><tbody>';

        // Set the base date as today in WP timezone
        $now = current_time('timestamp');

        for ($i = 0; $i < $count; $i++) {
            $name = 'User' . rand(1000, 9999);
            $email = strtolower($name) . '@example.com';

            // Calculate date for each review
            if ($i == 0) {
                $review_date = date('Y-m-d', $now);
            } else {
                $days_ago = $i * rand(3, 4);
                $review_date = date('Y-m-d', strtotime("-$days_ago days", $now));
            }

            echo '<tr>';
            echo '<td><input type="text" name="review['.$i.'][name]" value="'.$name.'" /></td>';
            echo '<td><input type="email" name="review['.$i.'][email]" value="'.$email.'" /></td>';
            echo '<td>★★★★★</td>';
            echo '<td><input type="text" name="review['.$i.'][title]" /></td>';
            echo '<td><textarea name="review['.$i.'][content]"></textarea></td>';
            echo '<td><input type="date" name="review['.$i.'][date]" value="'.$review_date.'" /></td>';
            echo '</tr>';
        }

        echo '</tbody></table><br><input type="submit" class="button-primary" value="Save Reviews (Coming Soon)" disabled />';
        // Keep the selected language in the next submit
        echo $lang_hidden;
        echo '</form>';
    }

    // Review count input, keep language selection
    echo '<form method="post"><p>Enter number of reviews to generate: 
        <input type="number" name="generate_count" min="1" max="50" required>
        '.$lang_hidden.'
        <input type="submit" class="button" value="Generate"></p></form></div>';
}
?>