<?php
/**
 * Buyer Logout Action
 */

session_start();
unset($_SESSION['customer_profile']);
unset($_SESSION['customer_id']);
unset($_SESSION['my_orders']);

// Redirect to home page
header('Location: ../index');
exit;
