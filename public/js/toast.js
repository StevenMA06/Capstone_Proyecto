document.querySelectorAll('[data-toast]').forEach((toast) => {
    let timer;
    const pause = () => window.clearTimeout(timer);
    const resume = () => {
        pause();
        if (!toast.matches(':hover') && !toast.contains(document.activeElement)) {
            timer = window.setTimeout(() => toast.remove(), 8000);
        }
    };
    toast.querySelector('[data-toast-close]').addEventListener('click', () => toast.remove());
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', () => window.setTimeout(resume, 0));
    resume();
});
