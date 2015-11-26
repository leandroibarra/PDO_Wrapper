<?php
/**
 * Validates if array is associative or is secuential.
 *
 * @param array $paArray
 * @return boolean
 */
function array_is_assoc(array $paArray) {
	return (bool) count(array_filter(array_keys($paArray), 'is_string'));
}
