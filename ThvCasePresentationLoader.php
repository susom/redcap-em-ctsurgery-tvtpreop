<?php
namespace Stanford\CtSurgery\ThvCasePresentationLoader;
require_once "emLoggerTrait.php";
require_once "SimpleXLSX.php";

class ThvCasePresentationLoader extends \ExternalModules\AbstractExternalModule {
    use emLoggerTrait;
    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}


}
