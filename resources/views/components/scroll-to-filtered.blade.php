{{-- When a list's own filters, sorting, or paging are in the URL, open the page at that list instead of the top. --}}
<script>
    (() => {
        // The page frame never scrolls (only the content column does), so undo any offset the browser restores into it.
        const resetFrame = () => {
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
            const frame = document.querySelector('main')?.parentElement;
            if (frame) {
                frame.scrollTop = 0;
            }
        };
        resetFrame();
        window.addEventListener('load', () => requestAnimationFrame(resetFrame));
        window.addEventListener('pageshow', resetFrame);

        if (window.location.hash) {
            return;
        }
        const params = new URLSearchParams(window.location.search);
        const section = [...document.querySelectorAll('[data-scroll-on-filter]')]
            .find((element) => element.dataset.scrollOnFilter.split(',').some((key) => params.get(key)));
        const content = section?.closest('main');
        if (! content) {
            return;
        }
        content.scrollTop += section.getBoundingClientRect().top - content.getBoundingClientRect().top - 16;
    })();
</script>
