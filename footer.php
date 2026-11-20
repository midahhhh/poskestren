        </main>

        <footer class="p-3 sm:px-6 sm:py-3.5 border-t border-[#d8e6de] bg-white text-xs text-[#64756D] flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left mt-auto">
            <span>Poskestren Al-Bahjah Bogor &mdash; SPK Triase SAW</span>
            <span>&copy; <?= date('Y'); ?> Al-Bahjah Health System</span>
        </footer>
    </div>

    <script>
        function konfirmasiHapus(pesan, url) {
            if (confirm(pesan || "Apakah Anda yakin ingin menghapus data ini?")) {
                window.location.href = url;
            }
        }
    </script>
<?php if (ob_get_level() > 0) ob_end_flush(); ?>
</body>
</html>
