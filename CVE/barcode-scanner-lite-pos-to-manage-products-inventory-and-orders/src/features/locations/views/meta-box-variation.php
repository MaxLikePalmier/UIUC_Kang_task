<style>
    .usbs_stock_location_levels>div>div:nth-child(2) {
        padding: 0 15px;
    }
</style>
<div class="form-field form-row form-row-full usbs_stock_location_levels">
    <div style="display: flex; justify-content: space-between;">
        <?php foreach ($this->get() as $key => $location) : ?>
            <?php if (trim($location->name)) : ?>
                <div style="width: 100%;">
                    <?php echo esc_html($location->name); ?><br />
                    <input type="text" name="v_<?php echo esc_attr($location->slug); ?>[<?php echo esc_attr($loop); ?>]" value="<?php echo esc_attr(get_post_meta($variation->ID, $location->slug, true)); ?>" />
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>