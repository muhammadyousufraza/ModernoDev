<?php 
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
defined('UNLIMITED_ELEMENTS_INC') or die('Restricted access');

class UniteCreatorAjaxSeach{
	
	public static $arrCurrentParams;
	public static $customSearchEnabled = false;
	public static $enableThirdPartyHooks = false;
	
	private $searchMetaKey = "";
	private $searchInTerms = false;
	private $strTerms = false;
	
	
	/**
	 * on posts response
	 */
	public function onPostsResponse($arrPosts, $value, $filters){
		
		if(GlobalsProviderUC::$isUnderAjaxSearch == false)
			return($arrPosts);
		
		$name = UniteFunctionsUC::getVal($value, "uc_posts_name");
		
		$args = GlobalsProviderUC::$lastQueryArgs;
		
		$maxItems = UniteFunctionsUC::getVal($args, "posts_per_page", 9);
		
		$numPosts = count($arrPosts);
		
		//if maximum reached - return the original
		
		if($numPosts >= $maxItems)
			return($arrPosts);
		
		$addCount = $maxItems - $numPosts;
		
		//search in meta
		
		if(!empty($this->searchMetaKey)){
			$arrPosts = $this->getPostsFromMetaQuery($arrPosts, $args, $addCount);
		
			$addCount = $maxItems - count($arrPosts);
		}
		
		if($this->searchInTerms == true && $addCount > 0)
			$arrPosts = $this->getPostsByTerms($arrPosts, $args, $addCount);
		
		
		return($arrPosts);
	}
	
	/**
	 * search posts by terms
	 */
	private function getPostsByTerms($arrPosts, $args, $maxPosts){
		
		if($this->searchInTerms == false)
			return($arrPosts);
		
		$search = $args["s"];
			
		unset($args["s"]);
		
		$postType = UniteFunctionsUC::getVal($args, "post_type");
		
		if(empty($postType))
			return($arrPosts);
		
		$arrTax = UniteFunctionsWPUC::getPostTypeTaxomonies($postType);
		
		if(empty($arrTax))
			return($arrPosts);
			
		$arrAllTaxNames = array_keys($arrTax);
				
		$arrTaxNames = UniteFunctionsUC::csvToArray($this->strTerms);
		
		if(!empty($arrTaxNames))
			$arrTaxNames = array_intersect($arrAllTaxNames, $arrTaxNames);
		else
			$arrTaxNames = $arrAllTaxNames;
			
		if(empty($arrTaxNames)){
		
			if(GlobalsProviderUC::$showPostsQueryDebug == true)
				dmp("taxonomies not found: {$this->strTerms}. please use some of those: ".print_r($arrAllTaxNames,true));
			
			return($arrPosts);
		}
		
		
		$arrTermsSearch = array();
		$arrTermsSearch["taxonomy"] = $arrTaxNames;
		$arrTermsSearch["search"] = $search;
		$arrTermsSearch["hide_empty"] = true;
		$arrTermsSearch["number"] = 50;
		//$arrTermsSearch["fields"] = "id=>name";
		
		$termsQuery = new WP_Term_Query();
		$arrTermsFound = $termsQuery->query($arrTermsSearch);

		if(empty($arrTermsFound)){
		
			if(GlobalsProviderUC::$showPostsQueryDebug == true){
				dmp("no terms found by: <b>$search</b>. Terms Query:");
				
				dmp($arrTermsSearch);
			}
			
			return($arrPosts);
		}
		
		
		$arrTaxQuery = UniteFunctionsWPUC::getTaxQueryFromTerms($arrTermsFound);
		
		$args = UniteFunctionsWPUC::mergeArgsTaxQuery($args,$arrTaxQuery);
				
		$query = new WP_Query();
		$query->query($args);
		
		$arrNewPosts = $query->posts;
		
		//debug output
		if(GlobalsProviderUC::$showPostsQueryDebug == true){
			
			dmp("Run Search By Terms Query: ");
			
			$strTerms = UniteFunctionsWPUC::getTermsTitlesString($arrTermsFound, true);
			
			dmp("Found Terms: ".count($arrTermsFound));
			
			dmp($strTerms);
			
			dmp($args);
			
			dmp("Found Posts: ".count($arrNewPosts));
		}
		
		
		if(empty($arrNewPosts))
			return($arrPosts);
		
		$arrPosts = array_merge($arrNewPosts, $arrPosts);
		
		return($arrPosts);
	}
	
	/**
	 * get posts from meta query
	 */
	private function getPostsFromMetaQuery($arrPosts, $args, $maxPosts){
		
		if(empty($this->searchMetaKey))
			return($arrPosts);
		
		$search = $args["s"];
			
		unset($args["s"]);
					
		$arrMetaItem = array(
		        'key'     => $this->searchMetaKey,
		        'value'   => $search,
		        'compare' => "LIKE"
		);
		
		$arrMetaQuery = array("relation"=>"OR",$arrMetaItem);
		
		$arrExistingMeta = UniteFunctionsUC::getVal($args, "meta_query",array());
					
		$args["meta_query"] = array_merge($arrExistingMeta, $arrMetaQuery);
		
		$query = new WP_Query();
		$query->query($args);
		
		$arrNewPosts = $query->posts;
		
		//debug output
		if(GlobalsProviderUC::$showPostsQueryDebug == true){
			
			dmp("Run Search By Meta Query: ");
			dmp($args);
			
			dmp("Found Posts: ".count($arrNewPosts));
		}
		
		$arrPosts = array_merge($arrPosts, $arrNewPosts);
		
		return($arrPosts);
	}
	
	
	/**
	 * supress third party filters except of this class ones
	 */
	public static function supressThirdPartyFilters(){
		
		
		//on the enable hooks setting - don't supress hooks
		
		if(self::$enableThirdPartyHooks === true)
			return(false);
				
		global $wp_filter;
		
		if(self::$customSearchEnabled == false){
			
			$wp_filter = array();
			return(false);
		}

		$arrKeys = array("uc_filter_posts_list");
		
		$newFilters = array();
		
		foreach($arrKeys as $key){
			
			$filter = UniteFunctionsUC::getVal($wp_filter, $key);
			
			if(!empty($filter))
				$newFilters[$key] = $filter;
		}
		
		$wp_filter = $newFilters;
		
	}
	
	
	/**
	 * init the ajax search - before the get posts accure, from ajax request
	 */
	public function initCustomAjaxSeach(UniteCreatorAddon $addon){
		
		$arrParams = $addon->getProcessedMainParamsValues(UniteCreatorParamsProcessor::PROCESS_TYPE_CONFIG);
		
		self::$arrCurrentParams = $arrParams;
				
		//enable hooks

		$enableHooks = UniteFunctionsUC::getVal($arrParams, "enable_third_party_hooks");
		$enableHooks = UniteFunctionsUC::strToBool($enableHooks);
		
		if($enableHooks == true)
			self::$enableThirdPartyHooks = true;
		
			
		//--- meta
		
		$searchInMeta = UniteFunctionsUC::getVal($arrParams, "search_in_meta");
		$searchInMeta = UniteFunctionsUC::strToBool($searchInMeta);
		
		$searchMetaKey = UniteFunctionsUC::getVal($arrParams, "searchin_meta_name");
				
		$applyModifyFilter = false;
		if($searchInMeta == true && !empty($searchMetaKey)){
		
			$applyModifyFilter = true;
			
			self::$customSearchEnabled = true;
			$this->searchMetaKey = $searchMetaKey;
		}
	
		//--- terms
		
		$searchInTerms = UniteFunctionsUC::getVal($arrParams, "search_in_terms");
		$searchInTerms = UniteFunctionsUC::strToBool($searchInTerms);
		
		if($searchInTerms == true){
			
			$applyModifyFilter = true;
			self::$customSearchEnabled = true;
			$this->searchInTerms = true;
			$this->strTerms = UniteFunctionsUC::getVal($arrParams, "search_in_taxonomy");
			
		}
		
		if($applyModifyFilter == true)
			UniteProviderFunctionsUC::addFilter("uc_filter_posts_list", array($this,"onPostsResponse"),10,3);
		
	}

}