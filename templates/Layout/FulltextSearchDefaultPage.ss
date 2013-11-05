<h1>$Title</h1>

$FulltextSearchForm

<% with Result %>
	<% if ErrorMessage %>
		<p class="message error">$ErrorMessage</p>
	<% end_if %>

	<% if Suggestion %>
		<p class="suggestion"><a href='$SuggestionLink'>Did you mean <strong>$Suggestion</strong>?</a></p>
	<% end_if %>

	<% if Matches %>
		<p class="total-results">$TotalMatches results found for your query.</p>

		<p class="metadata">
			Sort by: <select><% loop SortLinks %>
				<option value="$Link" <% if not Link %>selected="selected"<% end_if %>>$Caption</option>
			<% end_loop %></select>
		</p>

		<ul class="search-results">
			<% loop Matches %>
				<li class="summary">
					<h3><a href="$Link"><% if Parent %>$Parent.Title - <% end_if %>$Title</a></h3>
					<p class="date"><strong>Modified:</strong>  $LastEdited.Nice</p>
		
					<% if Excerpt %>
						<p class="excerpt">$Excerpt</p>
					<% else %>
						<% if Description %>
							<p class="description">$Description</p>
						<% end_if %>

					<% end_if %>
				</li>
			<% end_loop %>
		</ul>
	
		<% if Matches.MoreThanOnePage %>
			<div class='pagination'>
				<% if Matches.NotFirstPage %>
					<a class="prev" href="$Matches.PrevLink" title="View the previous page">Prev</a>
				<% end_if %>
			
				<% loop Matches.PaginationSummary %>
					<% if CurrentBool %><a class="current">$PageNum</a><% else %>
						<% if PageNum %>
							<a href="$Link">$PageNum</a>
						<% else %>
							<a class="ellipsis">...</a>
						<% end_if %>
					<% end_if %>
				<% end_loop %>
				
				<% if Matches.NotLastPage %>
					<a class="next" href="$Matches.NextLink" title="View the next page">Next</a>
				<% end_if %>					
			</div>
		<% end_if %>
	<% else %>
		<div class="search-message"><h3>No results found for your search</h3><p>Please use the form above to change your search phrase and criteria.</p></div>
	<% end_if %>
<% end_with %>