<?php
/**
 * Imports configuration of plugin from .xml created by config-plugin-export
 * moosh config-plugin-import <file>
 *
 * @example moosh config-plugin-import /tmp/Book_config_1608106580.xml
 *
 * @copyright  2020 onwards Jakub Kleban
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh\Command\Moodle39\Config;
use Moosh\MooshCommand;
use \DOMDocument;
use \core_plugin_manager;
use \stdClass;
use \context_system;

class ConfigPluginimport extends MooshCommand {

    private $inputfilepath;

    public function __construct() {
        parent::__construct('plugin-import', 'config');

        $this->addArgument('inputfile');

        $this->minArguments = 1;
        $this->maxArguments = 1;
    }

    public function execute() {
        global $CFG;

        require_once "$CFG->libdir/classes/plugin_manager.php";

        $this->inputfilepath = $this->arguments[0];
        if (substr($this->inputfilepath, 0, 2) == '..'){
            $this->inputfilepath = $this->cwd . '/' . $this->inputfilepath;
        }
        else if ($this->inputfilepath[0] == '.'){
            $this->inputfilepath = $this->cwd . substr($this->inputfilepath, 1);
        }
        else {
            $this->inputfilepath = $this->cwd . '/' . $this->inputfilepath;
        }

        if (is_file($this->inputfilepath)){
            if (!is_readable($this->inputfilepath)) {
                cli_error("Output file is not readable: $this->inputfilepath \n");
            }
        }
        else {
            cli_error("$this->inputfilepath is not a file \n");
        }

        $this->import_settings();
        exit(0);
    }

    private function import_settings() {
        global $DB;

        $context = context_system::instance();
        $fs = get_file_storage();

        $filename = basename($this->inputfilepath); //here
        $filenameparts = explode('_', $filename);

        $dom = new DOMDocument();
        $dom->load($this->inputfilepath);
        $configdom = $dom->documentElement;

        $component = $configdom->getAttribute('plugin');
        $settingsdom = $configdom->getElementsByTagName('setting');

        $settingscount = 0;
        if ($settingsdom->length) {
            foreach ($settingsdom as $setting) {
                $settingname = $setting->getAttribute('name');
                $settingvalue = $setting->nodeValue;

                if ($setting->hasAttribute('file')) {
                    $filename = ltrim($settingvalue, '/');
                    $fileinfo = array(
                        'contextid' => $context->id,
                        'component' => $component,
                        'filearea' => $settingname,
                        'itemid' => 0,
                        'filepath' => '/',
                        'filename' => $filename
                    );

                    if ($fs->file_exists($fileinfo['contextid'], $fileinfo['component'],
                        $fileinfo['filearea'], 0, $fileinfo['filepath'], $fileinfo['filename'])) {

                        $fs->delete_area_files($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'], 0);
                    }

                    $filepath = $this->inputfilepath.$setting->getAttribute('file');
                    $fs->create_file_from_pathname($fileinfo, $filepath);
                }

                $todb = new stdClass;
                $todb->plugin = $component;
                $todb->name = $settingname;
                $todb->value = $settingvalue;

                if ($existing = $DB->get_record('config_plugins', ['plugin' => $todb->plugin, 'name' => $todb->name])) {
                    $todb->id = $existing->id;
                    $DB->update_record('config_plugins', $todb);
                } else {
                    $DB->insert_record('config_plugins', $todb);
                }
                $settingscount++;
            }
            echo "$settingscount settings imported to $component \n";
        } else {
            echo "No settings to import \n";
        }
    }
}