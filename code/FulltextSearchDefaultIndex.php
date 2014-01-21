<?php

/**
 * @package fulltextsearchdefault
 */
class FulltextSearchDefaultIndex extends SolrIndex {
		
	/**
	 * Defines the indexed fields and datatypes. 
	 *
	 * To define custom fields, objects or attributes to search, subclass this
	 * class and override the init method in your subclass. Once you're defined
	 * a subclass then set using the config system what index you want the 
	 * search page to use.
	 *
	 * <code>
	 *	FulltextSearchDefaultPage:
	 *	  use_index: CustomSolrIndex
	 */
	public function init() {
		// add class names
		$this->addClass('SiteTree');
		$this->addClass('File');

		//
		$stored = (Director::isDev()) ? 'true' : 'false';

		// Fields are available for the search
		$this->addFulltextField('Title', 'Text'); //, array('boost' => '3')
		$this->addFulltextField('Link', 'Text');
		$this->addFulltextField('Content', 'HTMLText', array(
			'stored' => $stored
		));

		// sort field
		$this->addSortField('LastEdited', 'SSDatetime');

		$this->addFilterField('ShowInSearch');
		$this->addFilterField('CanViewType');
		$this->addFilterField('ViewerGroups.ID', 'Int');
		
		// spellcheck
		$this->addCopyField('SiteTree_Title', 'spellcheckData');
		$this->addCopyField('SiteTree_Content', 'spellcheckData');
		$this->addCopyField('File_Title', 'spellcheckData');

		// Aggregate fields for highlighting
		$this->addCopyField('SiteTree_Title', 'highlightData', array('maxChars' => 10000));
		$this->addCopyField('SiteTree_Content', 'highlightData', array('maxChars' => 10000));
		$this->addCopyField('File_Content', 'highlightData', array('maxChars' => 10000));

		// Don't support searching staging
		$this->excludeVariantState(array('SearchVariantVersioned' => 'Stage'));
	}

	/**
	 * Returns the name of the index.
	 *
	 * @return string
	 */
	public function getName() {
		$name = parent::getName();
		
		if(defined('SS_SOLR_INDEXNAME_SUFFIX')) {
			$name .= SS_SOLR_INDEXNAME_SUFFIX;
		}

		return $name;
	}

	/**
	 * Method for adding an object from the database into the index.
	 *
	 * @param DataObject
	 * @param string
	 * @param array
	 */
	protected function _addAs($object, $base, $options) {
		$includeSubs = $options['include_children'];

		$doc = new Apache_Solr_Document();

		// Always present fields
		$doc->setField('_documentid', $this->getDocumentID($object, $base, $includeSubs));

		$doc->setField('ID', $object->ID);
		$doc->setField('ClassName', $object->ClassName);

		foreach(SearchIntrospection::hierarchy(get_class($object), false) as $class) {
			$doc->addField('ClassHierarchy', $class);
		}

		// Add the user-specified fields
		foreach($this->getFieldsIterator() as $name => $field) {
			if($field['base'] == $base) {
				$this->_addField($doc, $object, $field);
			}
		}

		// CUSTOM Duplicate index combined fields ("Title" rather than 
		// "SiteTree_Title").
		//
		// This allows us to sort on these fields without deeper architectural 
		// changes to the fulltextsearch module. Note: We can't use <copyField> 
		// for this purpose because it only writes into multiValue=true
		// fields, and those can't be (reliably) sorted on.
		$this->_addField($doc, $object, $this->getCustomPropertyFieldData('Title', $object));
		$this->_addField($doc, $object, $this->getCustomPropertyFieldData('LastEdited', $object, 'SSDatetime'));	
		
		$this->getService()->addDocument($doc);

		return $doc;
	}

	/**
	 * Overridden field definitions to define additional custom fields like sort
	 * fields and additional functionality.
	 *
	 * @return string
	 */
	public function getFieldDefinitions() {
		$xml = parent::getFieldDefinitions();

		$stored = (Director::isDev()) ? "stored='true'" : "stored='false'";
		
		$xml .= "\n\n\t\t<!-- Additional custom fields for sorting, see PageSolrIndex.php -->";
		$xml .= "\n\t\t<field name='Title' type='alphaOnlySort' indexed='true' stored='true' />";
		$xml .= "\n\t\t<field name='LastEdited' type='tdate' indexed='true' stored='true' />";
		
		$xml .= "\n\n\t\t<!-- Additional custom fields for spell checking, see PageSolrIndex.php -->";
		$xml .= "\n\t\t<field name='spellcheckData' type='textSpell' $stored indexed='true' multiValued='true' />";
		$xml .= "\n\t\t<field name='highlightData' type='htmltext' stored='true' indexed='true' multiValued='true' />";

		return $xml;
	}

	/**
	 * Return a fake field definition (mainly to stay DRY).
	 *
	 * @param string
	 * @param DataObject
	 * @param string
	 * @param DBField
	 *
	 * @return array
	 */
	protected function getCustomPropertyFieldData($name, $object, $type = null, $customProperty = null) {
		if(!$type) {
			$type = 'Varchar';
		}

		return array(
			'name' => $name,
			'field' => $name,
			'fullfield' => $name,
			'base' => get_class($object),
			'origin' => get_class($object),
			'class' => get_class($object),
			'lookup_chain' => array(
				array('call' => 'property', 'property' => $customProperty ? $customProperty : $name)
			),
			'type' => $type,
			'multi_valued' => false,
			'extra_options' => array()
		);
	}
}	