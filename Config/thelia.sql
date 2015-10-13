
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- payment_recall_order
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `payment_recall_order`;

CREATE TABLE `payment_recall_order`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `customer_id` INTEGER NOT NULL,
    `recall_send` TINYINT(1) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `FI_paymentrecall_order_order` (`order_id`),
    INDEX `FI_paymentrecall_order_customer` (`customer_id`),
    CONSTRAINT `fk_paymentrecall_order_order`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_paymentrecall_order_customer`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- payment_recall_module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `payment_recall_module`;

CREATE TABLE `payment_recall_module`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `enable` TINYINT(1) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `FI_paymentrecall_module_module` (`module_id`),
    CONSTRAINT `fk_paymentrecall_module_module`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
