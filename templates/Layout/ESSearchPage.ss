<div class="typography">
    <% if $TotalHits > 0 %>
        Your query returned $TotalHits.
        <% if $Results %>
            <ul id="SearchResults">
                <% loop $Results %>
                <li>
                    <h3><a class="searchResultHeader" href="$Link">$Title</a></h3>
                    <% if $Content %>
                        <p>$Content</p>
                    <% end_if %>
                    <a class="readMoreLink" href="$Link" title="Read more about &quot;{$Title}&quot;">Read more about &quot;{$Title}&quot;...</a>
                </li>
                <% end_loop %>
            </ul>
        <% end_if %>

    <% else %>
        <p>Sorry, your search query did not return any results.</p>
    <% end_if %>

    

</div>
