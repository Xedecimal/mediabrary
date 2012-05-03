<?php

# http://www.freebase.com/queryeditor
# https://www.googleapis.com/freebase/v1/search?query=American%20Dad!&type=/tv/tv_program

/*
Single TV series, missing data still.
{
  "type":     "/tv/tv_program",
  "id": "/en/american_dad",
  "name":     null,
  "episodes": [{}],
  "seasons": [{}],
  "/common/topic/image": [{}]
}
 */

class Freebase
{
	const URL_SEARCH = 'https://www.googleapis.com/freebase/v1/search?query=%s&type=%s';

	function Search($query, $type)
	{
		return file_get_contents(sprintf(Freebase::URL_SEARCH,
			urlencode($query),
			urlencode($type)));
	}

	function Query()
	{
		file_get_contents();
	}
}
