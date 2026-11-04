import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

type ScrollableTabsProps = {
  children: ReactNode;
  className?: string;
  label?: string;
};

export function ScrollableTabs({ children, className = '', label = 'Tabs' }: ScrollableTabsProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);

  useEffect(() => {
    const viewport = viewportRef.current;
    if (!viewport) return undefined;

    const updateScrollState = () => {
      const maxScrollLeft = viewport.scrollWidth - viewport.clientWidth;
      setCanScrollLeft(viewport.scrollLeft > 1);
      setCanScrollRight(maxScrollLeft - viewport.scrollLeft > 1);
    };

    updateScrollState();
    viewport.addEventListener('scroll', updateScrollState, { passive: true });
    window.addEventListener('resize', updateScrollState);
    const resizeObserver = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(updateScrollState) : null;
    resizeObserver?.observe(viewport);
    const mutationObserver = typeof MutationObserver !== 'undefined' ? new MutationObserver(updateScrollState) : null;
    mutationObserver?.observe(viewport, { childList: true, subtree: true });

    return () => {
      viewport.removeEventListener('scroll', updateScrollState);
      window.removeEventListener('resize', updateScrollState);
      resizeObserver?.disconnect();
      mutationObserver?.disconnect();
    };
  }, []);

  const scroll = (direction: -1 | 1) => {
    viewportRef.current?.scrollBy({ left: direction * Math.max(220, viewportRef.current.clientWidth * 0.72), behavior: 'smooth' });
  };

  return <div className="scrollable-tabs-controls">
    <button className="scrollable-tabs-arrow" type="button" aria-label={`Scroll ${label} left`} disabled={!canScrollLeft} onClick={() => scroll(-1)}><ChevronLeft size={16} aria-hidden="true" /></button>
    <div ref={viewportRef} className={className}>{children}</div>
    <button className="scrollable-tabs-arrow" type="button" aria-label={`Scroll ${label} right`} disabled={!canScrollRight} onClick={() => scroll(1)}><ChevronRight size={16} aria-hidden="true" /></button>
  </div>;
}
