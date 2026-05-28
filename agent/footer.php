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

<!-- Searchable Combobox JS -->
<script src="<?= BASE_URL ?>/assets/js/combobox.js"></script>
<script>
    $(document).ready(function() {
        convertToSearchableCombobox('source', 'Select Origin...');
        convertToSearchableCombobox('destination', 'Select Destination...');
    });
</script>
</body>
</html>
