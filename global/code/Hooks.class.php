<?php

/**
 * The Account class. Added in 2.3.0; will replace the old accounts.php file.
 */


// -------------------------------------------------------------------------------------------------

namespace FormTools;

use PDOException;


class Hooks {

    /**
     * Called automatically after upgrading the Core, theme or module. This parses the entire Form Tools code base -
     * including any installed modules - and updates the list of available hooks. As of 2.1.0, the available hooks
     * are listed in the ft_hooks table, and the actual hook calls are in the hook_calls table (formerly the hooks
     * table).
     *
     * Before 2.1.0, the list of hooks was stored in /global/misc/hook_info.ini. However it only stored hooks for the
     * Core and habitually got out of date. This is a lot, lot better!
     */
    public static function updateAvailableHooks()
    {
        $ft_root = realpath(__DIR__ . "/../../");
        $hook_locations = array(
            // code hooks
            "process.php"        => "core",
            "global/code"        => "core",
            "global/api/api.php" => "api",
            "modules"            => "module",

            // template hooks
            "themes/default"  => "core"
        );

        $results = array(
            "code_hooks"     => array(),
            "template_hooks" => array()
        );

        while (list($file_or_folder, $component) = each($hook_locations)) {
            _ft_find_hooks("$ft_root/$file_or_folder", $ft_root, $component, $results);
        }


        self::clearHooks();
        self::addCodeHooks($results["code_hooks"]);
        self::addTemplateHooks($results["template_hooks"]);
    }


    /**
     * Our main process hooks function. This finds and calls ft_process_hook_call for each hook defined for
     * this event & calling function. It processes each one sequentially in order of priority.
     *
     * I changed the logic of this functionality in 2.1.0 - now I think it will work more intuitively.
     * Precisely what was being allowed to be overridden didn't make sense; this should work better now
     * (but all modules will need to be examined).
     *
     * "Priority" is actually very weird. Although it does allow hooks to define in what order they get
     * called, the priority to overriding the variables actually falls to the hooks with the LOWEST
     * priority. This can and should be adjusted, but right now there's no need for it. The fourth param
     * was added in part to solve this: that lets the calling function concatenate all overridden vars from
     * all calling functions and use all the data to determine what to do. This is used for the quicklinks
     * section on the main Submission Listing page.
     *
     * @param string $event the name of the event in the function calling the hook (e.g. "start", "end",
     *     "manage_files" etc.)
     * @param $vars whatever vars are being passed to the hooks from the context of the calling function
     * @param $overridable_vars whatever variables may be overridden by the hook
     * @param $overridable_vars_to_be_concatenated
     */
    public static function processHookCalls($event, $vars, $overridable_vars, $overridable_vars_to_be_concatenated = array())
    {
        $hooks_enabled = Core::areHooksEnabled();
        if (!$hooks_enabled) {
            return array();
        }

        $backtrace = debug_backtrace();
        $calling_function = $backtrace[1]["function"];

        // get the hooks associated with this core function and event
        $hooks = ft_get_hook_calls($event, "code", $calling_function);

        // extract the var passed from the calling function into the current scope
        $return_vals = array();
        foreach ($hooks as $hook_info) {

            // this clause was added in 2.1 - it should have been included in 2.0.x, but it was missed. This prevents any hooks
            // being processed for modules that are not enabled.
            $module_folder = $hook_info["module_folder"];
            if (!ft_check_module_enabled($module_folder)) {
                continue;
            }

            // add the hook info to the $template_vars for access by the hooked function. N.B. the "form_tools_"
            // prefix was added to reduce the likelihood of naming conflicts with variables in any Form Tools page
            $vars["form_tools_hook_info"] = $hook_info;
            $updated_vars = ft_process_hook_call($hook_info["module_folder"], $hook_info["hook_function"], $vars, $overridable_vars, $calling_function);

            // now return whatever values have been overwritten by the hooks
            foreach ($overridable_vars as $var_name) {
                if (array_key_exists($var_name, $updated_vars)) {
                    if (in_array($var_name, $overridable_vars_to_be_concatenated)) {
                        if (!array_key_exists($var_name, $return_vals)) {
                            $return_vals[$var_name] = array();
                        }
                        $return_vals[$var_name][] = $updated_vars[$var_name];
                    } else {
                        $return_vals[$var_name] = $updated_vars[$var_name];
                    }

                    // update $vars for any subsequent hook calls
                    if (array_key_exists($var_name, $vars)) {
                        $vars[$var_name] = $updated_vars[$var_name];
                    }
                }
            }
        }

        return $return_vals;
    }


    // --------------------------------------------------------------------------------------------

    private static function clearHooks()
    {
        Core::$db->query("TRUNCATE {PREFIX}hooks");
        Core::$db->execute();
    }

    private static function addCodeHooks($code_hooks)
    {
        Core::$db->beginTransaction();
        $db = Core::$db;

        foreach ($code_hooks as $hook_info)  {
            $db->query("
                INSERT INTO {PREFIX}hooks (hook_type, component, filepath, action_location, function_name, params, overridable)
                VALUES (:hook_type, :component, :file, :action_location, :function_name, :params, :overridable)
            ");
            $db->bindAll(array(
                ":hook_type" => "code",
                ":component" => $hook_info["component"],
                ":file" => $hook_info["file"],
                ":action_location" => $hook_info["action_location"],
                ":function_name" => $hook_info["function_name"],
                ":params" => implode(",", $hook_info["params"]),
                ":overridable" => implode(",", $hook_info["overridable"])
            ));
            $db->execute();
        }

        try {
            $db->processTransaction();
        } catch (PDOException $e) {
            $db->rollbackTransaction();
        }
    }

    private static function addTemplateHooks($template_hooks)
    {
        Core::$db->beginTransaction();
        foreach ($template_hooks as $hook_info) {
            Core::$db->query("
                INSERT INTO {PREFIX}hooks (hook_type, component, filepath, action_location, function_name, params, overridable)
                VALUES (:hook_type, :component, :template, :location, '', '', '')
            ");

            Core::$db->bindAll(array(
                ":hook_type" => "template",
                ":component" => $hook_info["component"],
                ":template" => $hook_info["template"],
                ":location" => $hook_info["location"]
            ));
        }
        try {
            Core::$db->processTransaction();
        } catch (PDOException $e) {
            Core::$db->rollbackTransaction();
        }
    }

}
