<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Environment;

use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\MultiArray;
function &_global(): MultiArray
{
    static $vars = new MultiArray();
    return $vars;
}
function add_global_var($key, $value = null)
{
    $global = _global();
    $global[$key] = $value;
    return $global;
}
function get_global_var($key = null): mixed
{
    $global = _global();
    if (is_null($key)) {
        return $global;
    }
    return $global[$key];
}
function get_global_var_inline_script($key = null, $func_name = null): string
{
    $var = get_global_var($key);
    if (is_null($func_name)) {
        $func_name = 'rsUtil_getGlobalVar';
        if ($key) {
            $func_name .= '_' . sanitize_title($key);
        }
    }
    ob_start();
    ?>( function() {
		if ( typeof <?php 
    echo $func_name;
    ?> === 'undefined' ) {
			const thisGlobalVar = <?php 
    echo wp_json_encode($var);
    ?>;
			window[`<?php 
    echo $func_name;
    ?>`] = function( key ) {
				return key ? ( thisGlobalVar?.[key] ?? null ) : thisGlobalVar;
			};
			document.dispatchEvent( new CustomEvent( '<?php 
    echo $func_name;
    ?>|ready', { detail: thisGlobalVar } ) );
		}
	} )();<?php 
    return ob_get_clean();
}
function get_global_var_footer_script($key = null, $func_name = null): string
{
    $script = get_global_var_inline_script($key, $func_name);
    return "<script type='text/javascript'>" . $script . "</script>";
}
