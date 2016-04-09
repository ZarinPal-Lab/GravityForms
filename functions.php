<?php if (!defined( 'ABSPATH' )) exit;

if( ! function_exists('rgget') ){
	function rgget( $name, $array = null ){
		if( ! isset($array) )
			$array = $_GET;
		if( isset($array[$name]) )
			return $array[$name];
		return '';
	}
}

if( ! function_exists('rgpost') ){
	function rgpost( $name, $do_stripslashes = true ){
		if( isset($_POST[$name]) )
			return $do_stripslashes ? stripslashes_deep($_POST[$name]) : $_POST[$name];
		return '';
	}
}

if( ! function_exists('rgar') ){
	function rgar( $array, $name ){
		if( isset($array[$name]) )
			return $array[$name];
		return '';
	}
}

if( ! function_exists('rgars') ){
	function rgars( $array, $name ){
		$names = explode('/', $name);
		$val = $array;
		foreach( (array) $names as $current_name ){
			$val = rgar($val, $current_name);
		}
		return $val;
	}
}

if( ! function_exists('rgempty') ){
	function rgempty( $name, $array = null ){
		if( ! $array ) {
			$array = $_POST;
		}
		$val = rgget($name, $array);
		return empty($val);
	}
}

if( ! function_exists('rgblank') ){
	function rgblank( $text ){
		return empty($text) && strval($text) != '0';
	}
}