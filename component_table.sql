CREATE TABLE `wp_components` (
    `component_id` INT(12) NOT NULL AUTO_INCREMENT,
    `component_sort` INT(12) NOT NULL,
    `component_status` VARCHAR(100) NOT NULL,
    `component_out_of_stock` BOOLEAN NOT NULL,
    `component_categorie` INT(12) NOT NULL,
    `component_item_number` INT(12) NOT NULL,
    `component_image` TEXT NOT NULL,
    `component_more_images` TEXT NOT NULL,
    `component_name` VARCHAR(255) NOT NULL,
    `component_descripion` TEXT NOT NULL,
    `component_purchasing_price` VARCHAR(255) NOT NULL,
    `component_retail_price` VARCHAR(255) NOT NULL,
    `component_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`component_id`)
);