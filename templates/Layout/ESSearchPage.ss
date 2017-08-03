<div class="typography">

    $SearchForm
    <% if $TotalHits > 0 %>
        <p>Your query returned $TotalHits. Showing the page $ResultStart to {$ResultEnd}.</p>
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

        <% if $Pagination.MoreThanOnePage %>
            <% if $Pagination.NotFirstPage %>
                <a class="prev" href="$Pagination.PrevLink">Prev</a>
            <% end_if %>
            <% loop $Pagination.Pages %>
                <% if $CurrentBool %>
                    $PageNum
                <% else %>
                    <% if $Link %>
                        <a href="$Link">$PageNum</a>
                    <% else %>
                        ...
                    <% end_if %>
                <% end_if %>
            <% end_loop %>
            <% if $Pagination.NotLastPage %>
                <a class="next" href="$Pagination.NextLink">Next</a>
            <% end_if %>
        <% end_if %>

    <% else %>
        <p>Sorry, your search query did not return any results.</p>
    <% end_if %>

    

</div>
