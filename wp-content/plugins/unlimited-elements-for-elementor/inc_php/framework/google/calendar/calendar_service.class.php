<?php

/**
 * https://developers.google.com/calendar/api/v3/reference
 */
class UEGoogleAPICalendarService extends UEGoogleAPIClient{

	
	/**
	 * convert times
	 * function not needed for now...
	 */
	private function convertItemTimes($arrTime,$targetTimezone){
		
		if(empty($arrTime))
			return($arrTime);
			
		$time = UniteFunctionsUC::getVal($arrTime, "dateTime");
		
		$sourceTimezone = UniteFunctionsUC::getVal($arrTime, "timeZone");
		
		if($sourceTimezone == $targetTimezone)
			return($arrTime);
		
		if(class_exists("DateTimeZone") == false)
			return($arrTime);
		
		$objSourceTimezone = new DateTimeZone($sourceTimezone);
		$objTargetTimezone = new DateTimeZone($targetTimezone);
		
		$objDate = new DateTime($time, $objSourceTimezone);
		
		$objDate->setTimezone($objTargetTimezone);
		
		$strDate = $objDate->format('Y-m-d\TH:i:s');
		
		$arrTime["dateTime"] = $strDate;
		
		
		return($arrTime);
	}
	
	
	/**
	 * convert timezones, from given to target timezone
	 */
	private function convertTimezones($response, $targetTimezone){
		
		if(empty($targetTimezone))
			return($response);
		
		$items = UniteFunctionsUC::getVal($response, "items");
		
		if(empty($items))
			return($response);
		
		foreach($items as $index=>$item){
			
			$item["start"] = $this->convertItemTimes($item["start"],$targetTimezone);
			$item["end"] = $this->convertItemTimes($item["end"],$targetTimezone);
			
			$items[$index] = $item;
		}
		
		$response["items"] = $items;
		
		return($response);
	}
	
	
	/**
	 * Get the events.
	 *
	 * @param string $calendarId
	 * @param array $params
	 *
	 * @return UEGoogleAPICalendarEvent[]
	 */
	public function getEvents($calendarId, $params = array(),$timezone = null){
		
		$calendarId = urlencode($calendarId);
		
		if(empty($timezone))
			$timezone = wp_timezone_string();
		
		//$params["timeZone"] = $timezone;
		
		$response = $this->get("/calendars/$calendarId/events", $params);
		
		$response = $this->convertTimezones($response, $timezone);
		
		$response = UEGoogleAPICalendarEvent::transformAll($response["items"]);
				
		return $response;
	}
	
	
	/**
	 * Get the base URL for the API.
	 *
	 * @return string
	 */
	protected function getBaseUrl(){

		return "https://www.googleapis.com/calendar/v3";
	}

}
