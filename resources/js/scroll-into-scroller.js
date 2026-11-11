/**
 * Horizontally reveal a child inside an overflow-x scroller without scrolling
 * the document/window.
 *
 * Avoid the DOM scrollIntoView API: browsers often scroll ancestor viewports
 * (including the page) — especially with smooth behavior on mobile/WebView —
 * which jumps pages like Evaluation away from the top after load.
 *
 * @param {HTMLElement} scroller
 * @param {HTMLElement} child
 * @param {'nearest'|'center'} [align='nearest']
 */
export function scrollChildIntoScrollerX(scroller, child, align = 'nearest') {
    if (! scroller || ! child) {
        return;
    }

    const scrollerRect = scroller.getBoundingClientRect();
    const childRect = child.getBoundingClientRect();
    if (scrollerRect.width <= 0 || childRect.width <= 0) {
        return;
    }

    let delta = 0;

    if (align === 'center') {
        delta = (childRect.left + childRect.width / 2)
            - (scrollerRect.left + scrollerRect.width / 2);
    } else if (childRect.left < scrollerRect.left) {
        delta = childRect.left - scrollerRect.left;
    } else if (childRect.right > scrollerRect.right) {
        delta = childRect.right - scrollerRect.right;
    }

    if (Math.abs(delta) < 1) {
        return;
    }

    if (typeof scroller.scrollBy === 'function') {
        scroller.scrollBy({ left: delta, behavior: 'auto' });
    } else {
        scroller.scrollLeft += delta;
    }
}
