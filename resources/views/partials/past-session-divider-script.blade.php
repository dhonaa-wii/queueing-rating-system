{{--
    For tables that filter their rows in the browser (search, track, section):
    the red past-session line can't be printed once on the server, since the
    filter decides which row is the first visible one. Each flagged row carries
    class "past-session-row" and its line in data-past-session-line; this puts
    one line above every visible run of them, and is re-run after each filter.
--}}
@once
    <script>
        window.placePastSessionDividers = function (root) {
            (root || document).querySelectorAll('tbody').forEach(function (tbody) {
                tbody.querySelectorAll('tr[data-past-session-auto]').forEach(function (row) { row.remove(); });

                var previousFlagged = false;

                Array.prototype.slice.call(tbody.rows).forEach(function (row) {
                    if (row.classList.contains('d-none') || row.style.display === 'none') return;

                    var flagged = row.classList.contains('past-session-row');

                    if (flagged && !previousFlagged && row.dataset.pastSessionLine) {
                        var divider = document.createElement('tr');
                        var cell = document.createElement('td');
                        var line = document.createElement('div');
                        var span = 0;

                        Array.prototype.forEach.call(row.cells, function (c) { span += c.colSpan || 1; });

                        divider.className = 'past-session-divider';
                        divider.setAttribute('data-past-session-auto', '');
                        cell.colSpan = span;
                        line.className = 'past-session-divider-line';
                        line.textContent = row.dataset.pastSessionLine;
                        cell.appendChild(line);
                        divider.appendChild(cell);
                        row.parentNode.insertBefore(divider, row);
                    }

                    previousFlagged = flagged;
                });
            });
        };

        document.addEventListener('DOMContentLoaded', function () {
            window.placePastSessionDividers(document);
        });
    </script>
@endonce
