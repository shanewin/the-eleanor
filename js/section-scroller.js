class SectionScroller {
  constructor() {
    this.sections = Array.from(document.querySelectorAll('.full-section'));
    this.upButton = document.querySelector('.section-nav .arrow.up');
    this.downButton = document.querySelector('.section-nav .arrow.down');
    this.currentIndex = 0;

    if (!this.sections.length || !this.upButton || !this.downButton) {
      return;
    }

    this.bindEvents();
    this.updateCurrentSection();
    this.updateButtons();
  }

  bindEvents() {
    this.upButton.addEventListener('click', () => this.previousSection());
    this.downButton.addEventListener('click', () => this.nextSection());

    document.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowUp') {
        this.previousSection();
      } else if (event.key === 'ArrowDown') {
        this.nextSection();
      }
    });

    let scrollTimeout = null;
    window.addEventListener('scroll', () => {
      window.clearTimeout(scrollTimeout);
      scrollTimeout = window.setTimeout(() => {
        this.updateCurrentSection();
        this.updateButtons();
      }, 80);
    });
  }

  nextSection() {
    if (this.currentIndex < this.sections.length - 1) {
      this.currentIndex += 1;
      this.scrollToSection(this.currentIndex);
    }
    this.updateButtons();
  }

  previousSection() {
    if (this.currentIndex > 0) {
      this.currentIndex -= 1;
      this.scrollToSection(this.currentIndex);
    }
    this.updateButtons();
  }

  scrollToSection(index) {
    this.sections[index].scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    });
  }

  updateCurrentSection() {
    const midpoint = window.scrollY + window.innerHeight / 2;
    this.sections.forEach((section, index) => {
      const top = section.offsetTop;
      const bottom = top + section.offsetHeight;
      if (midpoint >= top && midpoint < bottom) {
        this.currentIndex = index;
      }
    });
  }

  updateButtons() {
    this.upButton.disabled = this.currentIndex === 0;
    this.downButton.disabled = this.currentIndex === this.sections.length - 1;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new SectionScroller();
});

