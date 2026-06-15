document.addEventListener("DOMContentLoaded", function () {
    var revealElements = document.querySelectorAll(".reveal");

    if (!("IntersectionObserver" in window)) {
        revealElements.forEach(function (element) {
            element.classList.add("is-visible");
        });
        return;
    }

    var observer = new IntersectionObserver(
        function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    obs.unobserve(entry.target);
                }
            });
        },
        {
            threshold: 0.15,
            rootMargin: "0px 0px -30px 0px"
        }
    );

    revealElements.forEach(function (element) {
        observer.observe(element);
    });
});
