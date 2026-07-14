# Flowpath Hero Section

A modern, fully responsive fullscreen hero section for the Flowpath SaaS product built with React, Tailwind CSS, and Lucide React icons.

## Features

- **Fullscreen Hero Section**: Fills the viewport with `h-screen w-full overflow-hidden`
- **Background Video**: Looping, muted, autoplaying video with subtle dark overlay
- **Responsive Navigation**: 
  - Desktop: Horizontal navigation with dropdown menus
  - Mobile: Animated hamburger menu with smooth transitions
  - Logo with dual-diamond SVG design
- **Custom Styling**:
  - Liquid glass morphism effect (`.liquid-glass` class)
  - Smooth dropdown animations
  - Responsive typography scaling
- **Hero Content**:
  - Scalable headline with variable text opacity
  - Descriptive subheading
  - Two call-to-action buttons (solid white + glass style)
- **Font**: "Helvetica Now Text" with system font fallbacks
- **Full Responsiveness**: Optimized for sm, md, lg, xl breakpoints

## Project Structure

```
src/
├── components/
│   └── HeroSection.tsx    # Main hero section component
├── App.tsx                # Root application component
└── App.css                # Global styles and Tailwind directives
```

## Required Dependencies

```json
{
  "react": "^18.0.0",
  "lucide-react": "latest",
  "tailwindcss": "^3.0.0"
}
```

## Setup Instructions

### 1. Install Dependencies

```bash
npm install react lucide-react tailwindcss
```

### 2. Configure Tailwind CSS

Create a `tailwind.config.js` file:

```js
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

Create a `postcss.config.js` file:

```js
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

### 3. Import the Component

```tsx
import HeroSection from './components/HeroSection';

function App() {
  return <HeroSection />;
}
```

## Customization

### Colors
- Modify the overlay opacity in `bg-black/10`
- Adjust the liquid glass background colors
- Change button colors in the CTA buttons

### Content
Edit the following in `HeroSection.tsx`:
- Headline text in the `<h1>` element
- Subheading text in the `<p>` element
- Navigation items in `navConfig` object
- Button labels and links
- Video URL in the `<video>` element

### Font
The component uses "Helvetica Now Text" loaded from an external CDN. To change:
1. Update the `@import url()` in the `<style>` tag
2. Modify the `font-family` declaration

### Spacing & Sizing
All responsive spacing uses Tailwind's breakpoint system (sm, md, lg, xl). Adjust padding and text sizes in the relevant className attributes.

## Component Props

The `HeroSection` component is self-contained and doesn't require any props. All configuration is internal.

## Browser Support

- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance Notes

- The background video is optimized for streaming
- Backdrop filters are GPU-accelerated in modern browsers
- Mobile menu animations use GPU-friendly transforms
- Font is loaded asynchronously to prevent blocking
