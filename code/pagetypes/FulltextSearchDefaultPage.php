<?php

/**
 * @package fulltextsearchdefault
 */
class FulltextSearchDefaultPage extends Page {

	/**
	 * Generate link to a search with the specific arguments.
	 *
	 * @see framework/core/model/SiteTree#Link($action)
	 *
	 * @return string
	 */
	public function Link($args = null) {
		$link = parent::Link();

		if ($args) {
			if (is_array($args)) {
				$link .= "?" . http_build_query($args);
			}
		}


		return $link;
	}

	/**
	 * Returns the name of the {@link SolrIndex} for this search
	 *
	 * @return string
	 */
	public function getSearchIndexClass() {
		return $this->config()->get('index_class');
	}

	/**
	 * Returns the {@link SolrIndex}
	 *
	 * @return SolrIndex
	 */
	public function getSearchIndex() {
		return Injector::inst()->get($this->getSearchIndexClass());
	}

	/**
	 * @return int
	 */
	public function getPageSize() {
		return 20;
	}
}

/**
 * @package fulltextsearchdefault
 */
class FulltextSearchDefaultPage_Controller extends Page_Controller {

	/**
	 * Prepares the {@link SearchQuery}. Handles taking request arguments and
	 * generating the filters and excludes to manipulate the search query.
	 *
	 * @return SearchQuery
	 */
	protected function prepareQuery() {
		$params = $this->getSearchParams();
		$query = new SearchQuery();

		// Filters
		$query->search($params['q']);

		// Permission checks. CanView* checks are really basic, don't check for 
		// inheritance or enforce visibility for admins or other logged-in 
		// members (based on their many-many relationships).
		$query->exclude('SiteTree_ShowInSearch', false);
		$query->exclude('SiteTree_CanViewType', 'OnlyTheseUsers');
		$query->exclude('SiteTree_CanViewType', 'LoggedInUsers');

		// $query->filter('..', true)

		return $query;
	}

	/**
	 * Returns the facet counts for a query.
	 *
	 * Facets are added to a search index by editing the 
	 * {@link FulltextSearchDefaultIndex::init()} method to include new filter
	 * fields.
	 *
	 * <code>
	 *	// FulltextSearchDefaultIndex.php
	 *	$this->addFilterField('TestFacet');
	 * </code>
	 */
	public function getFacetsForQuery() {
		$query = $this->prepareQuery();
		$index = $this->getSearchIndex();

		// Perform search
		$res = $index->search(
			$query,
			0,
			0,
			array(
				'facet' => 'true',
				// 'facet.field[1]' => 'TestFacet',
				// 'facet.field[2]' => 'TestFacet',
				// 'facet.field[3]' => 'Participant'
			)
		);

		return (isset($res->Facets)) ? $res->Facets : false;
	}

	/**
	 * Return a search form that contains the original search criteria.
	 *
	 * Should be extended to include the filters and facets needed in your 
	 * context.
	 *
	 * @return Form
	 */
	public function FulltextSearchForm() {
		$params = $this->getSearchParams();

		$fields = new FieldList(
			new TextField('q', '')
		);

		$form = new Form($this,
			'SearchForm',
			$fields,
			new FieldList(new FormAction(
				'search', 'Search'
			))
		);

		$form->loadDataFrom($params);
		$form->setFormMethod('get');
		$form->disableSecurityToken();

		$form->setFormAction($this->Link());

		return $form;
	}


	/** 
	 * Filters out characters
	 *
	 * @param string $val
	 *
	 * @return string
	 */
	protected function filterSearchString($val) {
		if(is_array($val)) {
			foreach($val as $k => $v) $val[$k] = $this->filterSearchString($v);
			return $val;
		} else {
			return str_replace(
				array('<','>',"\n","\r",'(',')','@','$',';','|',',','%'),
				'',
				$val
			);
		}
	}

	/**
	 * @return array filtered version of the search parameters.
	 */
	public function getSearchParams() {
		$params = $this->filterSearchString($this->request->getVars());

		if(isset($params['url'])) {
			unset($params['url']);
		}
		
		$defaults = array(
			'q' => '', 
			'start' => 0, 
			'order' => 'relevancy'
		);

		$params = array_merge($defaults, $params);

		return $params;
	}

	/**
	 * Generate a list of search results, returns a ArrayList.
	 *
	 * @return unknown_type
	 */
	public function index() {
		Versioned::reading_stage('Live'); // Avoid querying draft

		$searchPage = $this->data();
		$params = $this->getSearchParams();
		$index = $this->getSearchIndex();
		$query = $this->prepareQuery();


		// filter by the date range if the filter exists. dp will give us the
		// option then we can generate the range from that.
		if(isset($params['dp']) && $params['dp']) {
			switch ($params['dp']) {
				case 'day':
					$range = new SearchQuery_Range(gmdate('Y-m-d\TH:i:s\Z', date('U', strtotime('-24 HOURS'))), "*");
					break;
				case 'week':
					$range = new SearchQuery_Range(gmdate('Y-m-d\TH:i:s\Z', date('U', strtotime('-7 DAYS'))), "*");
					break;
				case 'month':
					$range = new SearchQuery_Range(gmdate('Y-m-d\TH:i:s\Z', date('U', strtotime('-1 MONTH'))), "*");
					break;
				case 'year':
					$range = new SearchQuery_Range(gmdate('Y-m-d\TH:i:s\Z', date('U', strtotime('-365 DAY'))), "*");
					break;
				case 'custom':
					if(isset($params['cf']) && $params['cf']) {
						$from = gmdate('Y-m-d\TH:i:s\Z', 
							DBField::create_field('SS_Datetime', $params['cf'])->Format('U')
						);
					}
					else {
						$form = "*";
					}

					if(isset($params['ct']) && $params['ct']) {
						$to = gmdate('Y-m-d\TH:i:s\Z', 
							DBField::create_field('SS_Datetime', $params['cf'])->Format('U')
						);
					}
					else {
						$to = "*";
					}

					$range = new SearchQuery_Range($from, $to);
			}

			if(isset($range)) {
				$this->hasExtraFilter = true;

				$query->filter('LastEdited', $range);
			}
		}

		// Sort
		$sort = null;
		if(isset($params['order'])) {
			switch($params['order']) {
				case "title":
					$sort = 'Title asc,score desc';
					break;
				case "mostviewed":
					$sort = 'ViewCount desc,score desc';
					break;
				case "mostrecent":
					$sort = 'LastEdited desc,score desc';
					break;
				case "oldestfirst":
					$sort = 'LastEdited asc, score desc';
					break;
				case 'titledesc':
					$sort = 'Title desc, score desc';
					break;
			}
		}

		$page = (int)$params['start'] / $this->getPageSize();

		// Perform search
		$res = $index->search(
			$query,
			$page * $this->getPageSize(),
			$this->getPageSize(),
			array(
				'hl' => 'true',
				// Don't include title in highlighting, since its already part of the search result
				'hl.fl' => 'highlightData',
				// Use custom markers to allow HTML tag removal (replaced by <strong> further down)
				'hl.simple.pre' => ':highlight:',
				'hl.simple.post' => ':/highlight:',
				'hl.fragsize' => 100,
				'hl.snippets' => 3,
				'hl.mergeContiguous' => 'true',
				'sort' => $sort,
				'spellcheck' => 'true',
				'spellcheck.collate' => 'true',
				// Solr recommends usage of ExtendedDisMax for user-generated queries,
				// since its a more forgiving query format parser which should never generate execeptions
				'defType' => 'edismax',
			)
		);

		// Sort links
		$res->SortLinks = new ArrayList();
		$sortCaptions = array(
			"relevancy" => "Best Match", 
			"mostrecent" => "Date (newest first)",
			"oldestfirst" => "Date (oldest first)",
			"mostviewed" => "Most Viewed", 
			"title" => "A - Z",
			"titledesc" => "Z - A"
		);

		foreach($sortCaptions as $key => $caption) {
			$sortLink = array();
			$sortLink["Caption"] = $caption;
			if(!isset($params['order']) || $params['order'] != $key) {
				$sortLink["Link"] = $searchPage->Link(array_merge($params, array('order' => $key)));
			}
			$res->SortLinks->push(new ArrayData($sortLink));
		}
		
		// Suggestions
		if ($res->Suggestion) {
			$res->Suggestion = Convert::raw2xml($this->filterSearchString($res->Suggestion));
			$res->SuggestionLink = $searchPage->Link(array_merge($params, array('q' => $res->Suggestion)));
		}

		// Highlighting
		if($res->Matches) {
			foreach ($res->Matches as $item) {
				// Remove encoded HTML entities, and replace custom highlights
				// TODO Figure out how to filter out HTML in Solr highlight results
				if($item->Excerpt) {
					$item->Excerpt = strip_tags(html_entity_decode($item->Excerpt->value, ENT_COMPAT, 'UTF-8'));
					$item->Excerpt = str_replace(':highlight:', '<strong>', $item->Excerpt);
					$item->Excerpt = str_replace(':/highlight:', '</strong>', $item->Excerpt);	
				}
			}
		}

		// Paging and Counts
		$p = ($page * $this->pagesize) + 1;
		$res->ResultRange = $p . "-" . ($p + count($res->Matches) - 1);
		$res->TotalMatches = $res->Matches->getTotalItems();

		// Search summary
		if($params['q']) {
			$res->QueryAsText = Convert::raw2xml(sprintf('for "%s"', $params['q']));
		} else {
			$res->QueryAsText = "for anything";
		}

		return $this->customise(array(
			"Result" => $res
		))->renderWith(array("FulltextSearchDefaultPage", "Page"));
	}
}