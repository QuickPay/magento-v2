<?php

/**
 * For magento 2.3 use another callback class
 */
if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    include __DIR__ . "/CallbackM23.php";
} else {
    include __DIR__ . "/CallbackM22.php";
}
