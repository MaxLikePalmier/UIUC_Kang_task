<div style="display: flex; flex-wrap: wrap;">
    <?php foreach ($this->get() as $key => $location) : ?>
        <?php if (trim($location->name)) : ?>
            <div style="padding-right: 15px;">
                <?php echo esc_html($location->name); ?><br />
                <input type="text" name="<?php echo esc_attr($location->slug); ?>" value="<?php echo esc_attr(get_post_meta($post->ID, $location->slug, true)); ?>" />
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>