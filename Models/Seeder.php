<?php

#require_once 'FirePHPCore/fb.php';

/**
 * This is a grabs artifact IDs given seeds
 *
 * @author heather
 */
class Models_Seeder {
	#private $mendeley_profile_cache;

	private $TOTALIMPACT_MENDELEY_KEY;
	
    function __construct() {
		$password_ini_array = parse_ini_file("passwords.ini");
		$this->TOTALIMPACT_MENDELEY_KEY = $password_ini_array['TOTALIMPACT_MENDELEY_KEY'];
    #    $this->mendeley_profile_cache = new stdClass();
	}

	
    public function getMendeleyProfilePage($profileId) {
		#if (isset($this->mendeley_profile_cache->$profileId)) {
		#	$bodyProfilePage = $this->mendeley_profile_cache->$profileId;
		#} else {
			$mendeleyUrlProfilePage = "http://www.mendeley.com/profiles/" . $profileId . "/";
			$requestProfilePage = new HttpRequest($mendeleyUrlProfilePage, HTTP_METH_GET);
			$responseProfilePage = $requestProfilePage->send();
			$bodyProfilePage = $responseProfilePage->getBody();
			#$this->mendeley_profile_cache->$profileId = $bodyProfilePage;
		#}
		return $bodyProfilePage;
	}
			
    public function getMendeleyProfileArtifacts($profileId) {
		/* also get detailed journal page */
		/* For now only looks at the first page */

		$mendeleyUrlJournalPage = "http://www.mendeley.com/profiles/" . $profileId . "/publications/journal/";
		
		#var_dump($mendeleyUrlJournalPage);
		
		$requestJournalPage = new HttpRequest($mendeleyUrlJournalPage, HTTP_METH_GET);
		$responseJournalPage = $requestJournalPage->send();
		$bodyJournalPage = $responseJournalPage->getBody();

		$bodyProfilePage = $this->getMendeleyProfilePage($profileId);
		
		$body = $bodyProfilePage . $bodyJournalPage;
		#var_dump($body);
		
		$regex_pattern = '/<div class="[publication|document_desc].*<.span>\W*<.div>/Ums';
		preg_match_all($regex_pattern, $body, $publications, PREG_SET_ORDER);
		#var_dump($publications);

		$id_list = array();
		foreach ($publications as $publication) {			
			$regex_pattern = '/rft_id=info.*%2F(.*)(&|").*span/U';
			preg_match($regex_pattern, $publication[0], $matches);

			if ($matches) {
				$id = str_replace('%2F', '/', $matches[1]);
    			$id_list[] = $id;
			} else {
				$regex_pattern = '|http://www.mendeley.com/research/(.*)"|U';
				preg_match_all($regex_pattern, $publication[0], $matches, PREG_SET_ORDER);
				/* FB::log($matches); */
				
				#var_dump($matches);
		
				foreach ($matches as $match) {
			
					$url = $match[0];
					$url = str_replace('"', '', $url);  /* remove last double quotes */
			
					$urltitle = $match[1];
					$titlewords = str_replace("-", '%20', $urltitle);  /* swap dashes for spaces, then encode */
					$titlewords = str_replace("/", '', $titlewords);  /* remove last slash */
			
				    $MENDELEY_LOOKUP_FROM_DOI_URL_PART1 = "http://api.mendeley.com/oapi/documents/search/title%3A";
					$MENDELEY_LOOKUP_FROM_DOI_URL_PART2 = "/?consumer_key=" . $this->TOTALIMPACT_MENDELEY_KEY;
			
					$mendeleyUrlLookupPage = $MENDELEY_LOOKUP_FROM_DOI_URL_PART1 . $titlewords . $MENDELEY_LOOKUP_FROM_DOI_URL_PART2;
					$requestLookupPage = new HttpRequest($mendeleyUrlLookupPage, HTTP_METH_GET);
					$requestLookupPage->setOptions(array("timeout"=>10, "useragent"=>"total-Impact"));
			
					try {
		    			$responseLookupPage = $requestLookupPage->send();
					} catch (HttpException $ex) {
		    			# echo $ex->getMessage();
						continue;
					}
			
					$bodyLookupPage = $responseLookupPage->getBody();
					$bodyLookupJson = json_decode($bodyLookupPage);

					foreach ($bodyLookupJson->documents as $artifact) {
						if (isset($artifact->mendeley_url)) {

							if ($artifact->mendeley_url === $url) {
				    			$id_list[] = $artifact->uuid;
							}
						}
					}	
				}
			}				
		}
		
		return array_unique($id_list);
	}
	
    public function getMendeleyProfileGroupsDisplay($profileId) {
		$bodyProfilePage = $this->getMendeleyProfilePage($profileId);
		$regex_pattern = '/groups.(\d+)\/.*">(.*)</U';
		preg_match_all($regex_pattern, $bodyProfilePage, $matches, PREG_SET_ORDER);
		$combo = "";
		shuffle($matches);
		$sliced = array_slice($matches, 0, 7);
		foreach ($sliced as $match) {
			$id = $match[1];
			$title = $match[2];
			$combo .= '<a target="_blank" href="./update.php?quickreport&name=' . $title . '&mendeleygroup=' . $id . '">' . $title . '</a><br/>';
		}
		return $combo;
	}

    public function getMendeleyProfileContactsDisplay($profileId) {
		$bodyProfilePage = $this->getMendeleyProfilePage($profileId);
		$regex_pattern = '/profiles.(\S+)\/.*profile">(.*)<\/a>/U';
		preg_match_all($regex_pattern, $bodyProfilePage, $matches, PREG_SET_ORDER);
		$combo = '<a target="_blank" href="./update.php?quickreport&name=' . $profileId . '&mendeleyprofile=' . $profileId . '">' . $profileId . '</a><br/>';
		foreach ($matches as $match) {
			$id = $match[1];
			$title = $match[2];
			$combo .= '<a target="_blank" href="./update.php?quickreport&name=' . $title . '&mendeleyprofile=' . $id . '">' . $title . '</a><br/>';
		}
		return $combo;
	}
	
    public function getMendeleyGroupArtifacts($groupId) {
	
	    $MENDELEY_LOOKUP_FROM_DOI_URL_PART1 = "http://api.mendeley.com/oapi/documents/groups/";
		$MENDELEY_LOOKUP_FROM_DOI_URL_PART2 = "/docs/?details=true&items=100&consumer_key=" . $this->TOTALIMPACT_MENDELEY_KEY;
		$mendeleyUrlGroupPage = $MENDELEY_LOOKUP_FROM_DOI_URL_PART1 . $groupId . $MENDELEY_LOOKUP_FROM_DOI_URL_PART2;
		$requestGroupPage = new HttpRequest($mendeleyUrlGroupPage, HTTP_METH_GET);
		$responseGroupPage = $requestGroupPage->send();
		$bodyGroupPage = $responseGroupPage->getBody();
		$body = json_decode($bodyGroupPage);

		$id_list = array();
		foreach ($body->documents as $artifact) {
			if (isset($artifact->url)) {
	    		$id_list[] = $artifact->uuid;
	    		#$id_list[] = $artifact->url;
			}
		}
		
		return $id_list;
	}
	
    public function getSlideshareProfileArtifacts($profileId) {
		$slideshareProfilePage = "http://www.slideshare.net/" . $profileId . "/presentations";
		$requestProfilePage = new HttpRequest($slideshareProfilePage, HTTP_METH_GET);
		$responseProfilePage = $requestProfilePage->send();
		$body = $responseProfilePage->getBody();
		
		$regex_pattern = '/<a title=.* href="(.' . $profileId . '.*)"/U';
		preg_match_all($regex_pattern, $body, $matches);
		$artifactIds = $matches[1];
		foreach ($artifactIds as &$value) {
		    $value = "http://www.slideshare.net" . $value;
		}
		return $artifactIds;
	}
 
    public function getDryadProfileArtifacts($profileId) {
		$profileId = urlencode(strtolower($profileId));
		$profileId = str_replace('+', '%5C+', $profileId);
		$dryadProfilePage = "http://datadryad.org/discover?field=dc.contributor.author_filter&fq=dc.contributor.author_filter%3A" . $profileId;
		$requestProfilePage = new HttpRequest($dryadProfilePage, HTTP_METH_GET);
		$responseProfilePage = $requestProfilePage->send();
		$body = $responseProfilePage->getBody();
		
		$regex_pattern = '/(10.5061.dryad.*)<.span/U';
		preg_match_all($regex_pattern, $body, $matches);
		$artifactIds = $matches[1];
		return $artifactIds;
	}

    public function getPubMedGrantArtifacts($grantId) {
		$grantId = urlencode(strtolower($grantId));
		$grantIdString = "(" . $grantId . "[grant number] OR " . $grantId . "-*[grant number])";
		$grantEsearchUrl = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi" . "?db=pubmed&retmax=100&tool=total-impact.org&email=total-impact@googlegroups.com&term=" . urlencode($grantIdString);
		$requestProfilePage = new HttpRequest($grantEsearchUrl, HTTP_METH_GET);
		$responseProfilePage = $requestProfilePage->send();
		$body = $responseProfilePage->getBody();

		$regex_pattern = '/<Id>(.*)<\/Id>/U';
		preg_match_all($regex_pattern, $body, $matches);
		$artifactIds = $matches[1];
		return $artifactIds;
	}
	    
}

	/*
	$a = new Models_Seeder();
	var_dump($a->getMendeleyProfileArtifacts("bill-hooker")); 
	#var_dump($a->getMendeleyProfileArtifacts("iain-hrynaszkiewicz")); 
	#var_dump($a->getMendeleyProfileArtifacts("heather-piwowar"));
	*/
?>
