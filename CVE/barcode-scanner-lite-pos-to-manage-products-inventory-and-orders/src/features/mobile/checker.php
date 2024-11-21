<?php
echo json_encode(array(
    "success" => true,
    "blogName" => get_bloginfo("name"),
    "logoUrl" =>  esc_url(wp_get_attachment_url(get_theme_mod('custom_logo'))),
    "home" => get_home_url()
));
