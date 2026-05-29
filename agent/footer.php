<?php
/**
 * Agent Portal Footer
 */
?>
            </div> <!-- Close py-5 -->
        </div> <!-- Close col-md-9 col-lg-10 -->
    </div> <!-- Close row -->
</div> <!-- Close container-fluid -->

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Select2 & DataTables JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Apply Select2 to selects (excluding seat layout controls and visual builders)
        $('select.select2-searchable').select2({
            width: '100%',
            dropdownParent: $('.modal:visible').length ? $('.modal:visible') : null
        });

        // Initialize DataTables
        $('.datatable-swift').DataTable({
            responsive: true,
            pageLength: 10,
            order: []
        });
    });
</script>
</body>
</html>
