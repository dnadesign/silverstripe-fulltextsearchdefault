# FullTextSearchDefault module

Extends the [FullTextSearch](https://github.com/silverstripe-labs/silverstripe-fulltextsearch)
module with a default configuration that provides a basic Solr configuration and
search results page.

Since the FullTextSearch module is a work in progress and subject to change, 
this module should be considered alpha. In the near future, the FulltextSearch
module may also provide this functionality out of the box.

## Installation

To start using Solr, install this module along with the fulltextsearch module
and run dev/build. After running the database rebuild, start the Solr server up. 

The fulltextsearch module provides a basic server suitable for testing. To start 
that run:

	cd fulltextsearch/thirdparty/solr/server/ && java -jar start.jar &

If Solr has started up and is running, we should first make sure the data 
directory exists (default: /data/solr) or configured by the SOLR_BASE_PATH
value in your `_ss_environment.php` file.

	mkdir /data/solr

Ensure that your web server can write to that folder. Then to populate that
directory run the configure task:

	sake dev/tasks/Solr_Configure

That will populate the `/data/solr/FulltextSearchDefaultIndex/conf` directory 
with the configuration files such as `schema.xml` and `solrconfig.xml`. More
information about these files can be found in the [Solr Manual](http://lucene.apache.org/solr/)

For more information about the SilverStripe wrapper around Solr consult the 
fulltextsearch module directly.

## Usage

This module will provide a new page type in the CMS called `FulltextSearchDefaultPage`
along with a default index. More about customizing the index is below. 

Both the page type and the index are designed to be starting points for 
developers to use to extend to make the most of their Solr service.

### Extending the built in indexes

To index your own dataobjects, subclass the `FulltextSearchDefaultIndex` class
and add your own types in. 
	
	:::php
	<?php

	class CustomFulltextSearchIndex extends FulltextSearchDefaultIndex {

		public function init() {
			parent::init();

			// adds a new dataobject to the search
			$this->addClass('MyDataObjectProduct');

			// define a new field to search
			$this->addFulltextField('ProductDescription', 'HTMLText')
		}
	}

Tell the `FulltextSearchDefaultPage` that you want to use your new index by
using the `Config` api.
	
	:::yml
	FulltextSearchDefaultPage:
 	  index_class: CustomFulltextSearchIndex

