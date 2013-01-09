<?php
/*
 * Created on Jan 7, 2013
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

class PholdBoxTestBase extends PHPUnit_Framework_TestCase
{
	protected $event;
	protected function setUp(){
		$_SERVER["SERVER_NAME"] = "pholdbox.local.dev";
		require("config/config.php");
		require("system/RCProcessor.php");
		require("system/PholdBoxBaseObj.php");
		require("system/Event.php");
		require("system/Model.php");
		require("system/PhORM.php");
		require("system/PholdBoxSessionManager.php");
		
		//look for site specific configs and merge them.
		if(isset($SYSTEM[$_SERVER["SERVER_NAME"]]))
		{
			$SYSTEM = array_replace_recursive($SYSTEM, $SYSTEM[$_SERVER["SERVER_NAME"]]);
		}
		
		$VERSION = "1.0 beta";
		
		$RCProcessor = new system\RCProcessor();
		$GLOBALS["rc"] = $RCProcessor->getCollection();
		$GLOBALS["SESSION"] = new system\PholdBoxSessionManager();
		$GLOBALS["SESSION"]->loadSession();
		
		$GLOBALS["rc"]["PB_VERSION"] = $VERSION;
		
		$SYSTEM["debugger"]["startTime"] = microtime(true);
		$SYSTEM["debugger"]["userStack"] = array();
		$SYSTEM["debugger"]["stack"] = array();
		
		$event = new system\Event($SYSTEM["default_layout"]);
		if(array_key_exists("event", $GLOBALS["rc"]))
		{
			//$event->processEvent($GLOBALS["rc"]["event"]);
		}
		else
		{	
			//$event->processEvent($SYSTEM["default_event"]);
		}
		$SYSTEM["debugger"]["endTime"] = microtime(true);
		
		//$event->renderDebugger();
		
	}
	
}
?>
