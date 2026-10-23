{{-- Footer of every exported report (Dompdf): the timestamp on the left, and
     under it the "ARPQRS · college · academic year" line centered. Goes inside
     the page's own position:fixed .footer so it repeats on every page. Styles
     are inline so each PDF's own div/td rules can't reach it.
     Expects $footerText and $generatedAt. --}}
<div style="text-align: left; margin: 0 0 2px;">Generated {{ $generatedAt }}</div>
<div style="text-align: center; margin: 0;">{{ $footerText }}</div>
