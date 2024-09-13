<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
defined('UNLIMITED_ELEMENTS_INC') or die('Restricted access');


require HelperUC::getPathViewObject("settings_view.class");

class UniteCreatorViewElementorSettings extends UniteCreatorSettingsView{


	/**
	 * modify custom settings - function for override
	 */
	protected function modifyCustomSettings($objSettings){

		$objSettings = HelperProviderUC::modifyGeneralSettings_memoryLimit($objSettings);

		//show the setting that was hidden in first place
		if(GlobalsUC::$inDev == true)	//dynamic visibility
			$objSettings->updateSettingProperty("enable_dynamic_visibility", "hidden", "false");

		if(GlobalsUnlimitedElements::$enableForms == false){
			$objSettings->hideSetting("enable_form_entries");
			$objSettings->hideSetting("save_form_logs");

			$objSettings->hideSap("forms");
		}

		if(GlobalsUnlimitedElements::$enableGoogleAPI == false){
			
			$objSettings->hideSetting("google_connect_heading");
			$objSettings->hideSetting("google_connect_desc");
			$objSettings->hideSetting("google_connect_integration");
			
			$objSettings->hideSetting("google_api_heading");
			$objSettings->hideSetting("google_api_key");
		}

		if(GlobalsUnlimitedElements::$enableWeatherAPI == false){
			$objSettings->hideSetting("openweather_api_heading");
			$objSettings->hideSetting("openweather_api_key");
		}

		if(GlobalsUnlimitedElements::$enableCurrencyAPI == false){
			$objSettings->hideSetting("exchangerate_api_heading");
			$objSettings->hideSetting("exchangerate_api_key");
		}

		$isWpmlExists = UniteCreatorWpmlIntegrate::isWpmlExists();

		//enable wpml integration settings
		if($isWpmlExists == true){

			$objSettings->updateSettingProperty("wpml_heading", "hidden", "false");
			$objSettings->updateSettingProperty("wpml_button", "hidden", "false");

		}

		return($objSettings);
	}


	/**
	 * constructor
	 */
	public function __construct(){

		$this->headerTitle = esc_html__("General Settings", "unlimited-elements-for-elementor");
		$this->isModeCustomSettings = true;
		$this->customSettingsXmlFile = HelperProviderCoreUC_EL::$filepathGeneralSettings;
		$this->customSettingsKey = "unlimited_elements_general_settings";


		//set settings
		$this->display();
	}


}

new UniteCreatorViewElementorSettings();
