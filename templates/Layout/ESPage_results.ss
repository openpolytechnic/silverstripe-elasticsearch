<div class="typography">

    <% if $Results %>
        <% with $Results %>
            Your query returned $TotalHits results $Count.
            <ul id="SearchResults">
                <% loop $Results %>
                <li>
                    <h3><a class="searchResultHeader" href="$URL">$Title</a></h3>
                    <% if $Content %>
                        $Content.FirstParagraph(html)
                    <% end_if %>
                    <a class="readMoreLink" href="$Link" title="Read more about &quot;{$Title}&quot;">Read more about &quot;{$Title}&quot;...</a>
                </li>
                <% end_loop %>
            </ul>
        <% end_with %>

        <div id="PageNumbers">

            <% if $PreviousResults %>
                <a class="prev" href="$PrevLink" title="View the previous page">Previous Page</a>
            <% end_if %>
            |
            <% if $NextResults %>
                <a class="next" href="$NextLink" title="View the next page">Next Page</a>
            <% end_if %>
        </div>
    <% else %>

        <p>Sorry, your search query did not return any results.</p>

    <% end_if %>

    

</div>
