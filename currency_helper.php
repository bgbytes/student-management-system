<?php
/**
 * Real-Time USD to INR Currency Engine
 * EduManage Enterprise Portal
 * 
 * Provides utility blocks to convert inputs in USD ($) to INR (₹)
 * at a static benchmark rate of 1 USD = 85 INR, and formats financial parameters.
 */

/**
 * Intercepts value and converts USD to INR if a USD indicator is found.
 * If input contains '$' prefix/suffix, it extracts the number, multiplies by 85,
 * and returns the INR result. Otherwise, returns the input numeric float.
 * 
 * @param mixed $input Value to convert
 * @param bool $force_usd If true, treats the numeric input as USD regardless of symbols
 * @return float Equivalent value in INR
 */
function convert_usd_to_inr($input, $force_usd = false) {
    if (is_string($input)) {
        $clean_input = trim($input);
        if (strpos($clean_input, '$') !== false) {
            $num_str = str_replace('$', '', $clean_input);
            return floatval($num_str) * 85;
        }
    }
    
    $value = floatval($input);
    if ($force_usd) {
        return $value * 85;
    }
    
    return $value;
}

/**
 * Formats a value in Indian Rupees (INR) with the ₹ symbol.
 * 
 * @param float $amount Amount in INR
 * @return string Formatted string
 */
function format_inr($amount) {
    return '₹' . number_format(floatval($amount), 2);
}
?>
