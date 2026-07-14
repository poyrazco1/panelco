import React, { useState } from 'react';
import { Menu, X, ChevronDown } from 'lucide-react';

// Diamond SVG Logo
const DiamondLogo = () => (
  <svg
    width="28"
    height="28"
    viewBox="0 0 28 28"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    className="inline-block mr-2"
  >
    {/* First diamond at 0.9 opacity */}
    <path
      d="M14 2L24 14L14 26L4 14Z"
      fill="white"
      opacity="0.9"
    />
    {/* Second diamond at 0.5 opacity, slightly offset */}
    <path
      d="M14 6L20 14L14 22L8 14Z"
      fill="white"
      opacity="0.5"
    />
  </svg>
);

interface DropdownItem {
  label: string;
  href?: string;
}

interface NavDropdown {
  label: string;
  items: DropdownItem[];
}

interface NavConfig {
  [key: string]: NavDropdown;
}

const navConfig: NavConfig = {
  Product: {
    label: 'Product',
    items: [
      { label: 'Connections' },
      { label: 'Workflows' },
      { label: 'Insights' },
    ],
  },
  Solutions: {
    label: 'Solutions',
    items: [
      { label: 'Guides' },
      { label: 'Use cases' },
      { label: 'API reference' },
    ],
  },
  About: {
    label: 'About',
    items: [
      { label: 'Our story' },
      { label: 'Open roles' },
      { label: 'Reach us' },
    ],
  },
};

interface DropdownProps {
  isOpen: boolean;
  items: DropdownItem[];
}

const Dropdown: React.FC<DropdownProps> = ({ isOpen, items }) => {
  if (!isOpen) return null;

  return (
    <div
      className="!absolute top-full left-0 liquid-glass rounded-xl py-3 px-2 min-w-[160px] shadow-xl animate-dropdown z-50"
      style={{
        position: 'absolute',
      }}
    >
      {items.map((item, index) => (
        <a
          key={index}
          href={item.href || '#'}
          className="block text-white/80 hover:text-white text-sm rounded-lg hover:bg-white/5 px-3 py-2 transition-colors"
        >
          {item.label}
        </a>
      ))}
    </div>
  );
};

interface NavItemProps {
  label: string;
  dropdown?: NavDropdown;
}

const NavItem: React.FC<NavItemProps> = ({ label, dropdown }) => {
  const [isOpen, setIsOpen] = useState(false);

  if (!dropdown) {
    return (
      <a href="#" className="text-white/90 hover:text-white text-sm font-medium transition-colors">
        {label}
      </a>
    );
  }

  return (
    <div
      className="relative"
      onMouseEnter={() => setIsOpen(true)}
      onMouseLeave={() => setIsOpen(false)}
    >
      <button className="flex items-center gap-1.5 text-white/90 hover:text-white text-sm font-medium transition-colors">
        {label}
        <ChevronDown
          size={14}
          className={`transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`}
        />
      </button>
      <Dropdown isOpen={isOpen} items={dropdown.items} />
    </div>
  );
};

interface MobileMenuProps {
  isOpen: boolean;
  navConfig: NavConfig;
  onClose: () => void;
}

const MobileMenu: React.FC<MobileMenuProps> = ({ isOpen, navConfig, onClose }) => {
  return (
    <div
      className={`absolute top-full left-0 right-0 mx-4 bg-[#2C221C]/95 backdrop-blur-xl rounded-2xl p-6 overflow-hidden transition-all duration-400 origin-top ${
        isOpen ? 'scale-y-100 opacity-100' : 'scale-y-95 opacity-0 pointer-events-none'
      }`}
      style={{
        transformOrigin: 'top center',
        transform: isOpen ? 'scaleY(1)' : 'scaleY(0.95)',
      }}
    >
      <div className="space-y-4">
        {Object.entries(navConfig).map(([key, config]) => (
          <div key={key}>
            <p className="text-white font-medium text-sm mb-2">{config.label}</p>
            <div className="space-y-2 ml-4">
              {config.items.map((item, index) => (
                <a
                  key={index}
                  href={item.href || '#'}
                  className="block text-white/70 hover:text-white text-sm transition-colors"
                >
                  {item.label}
                </a>
              ))}
            </div>
          </div>
        ))}
      </div>

      <div className="border-t border-white/10 mt-6 pt-6 flex flex-col gap-3">
        <a
          href="#"
          className="text-white/90 hover:text-white text-sm font-medium transition-colors text-center"
        >
          Log in
        </a>
        <button className="w-full liquid-glass rounded-full px-5 py-2 text-white text-sm font-medium hover:bg-white/10 transition-colors">
          Try it free
        </button>
      </div>
    </div>
  );
};

export const HeroSection: React.FC = () => {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  return (
    <>
      <style>{`
        @import url('https://db.onlinewebfonts.com/c/08e020de1811ec4489f82d1247a42c09?family=Helvetica+Now+Text');

        * {
          font-family: 'Helvetica Now Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .liquid-glass {
          background: rgba(255, 255, 255, 0.01);
          background-blend-mode: luminosity;
          backdrop-filter: blur(4px);
          -webkit-backdrop-filter: blur(4px);
          border: none;
          box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.1);
          position: relative;
          overflow: hidden;
        }

        .liquid-glass::before {
          content: '';
          position: absolute;
          inset: 0;
          border-radius: inherit;
          padding: 1.4px;
          background: linear-gradient(180deg,
            rgba(255,255,255,0.45) 0%, rgba(255,255,255,0.15) 20%,
            rgba(255,255,255,0) 40%, rgba(255,255,255,0) 60%,
            rgba(255,255,255,0.15) 80%, rgba(255,255,255,0.45) 100%);
          -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
          -webkit-mask-composite: xor;
          mask-composite: exclude;
          pointer-events: none;
        }

        @keyframes dropdown-in {
          from { opacity: 0; transform: translateY(-4px) scale(0.96); }
          to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .animate-dropdown {
          animation: dropdown-in 0.2s ease-out;
        }

        .duration-400 {
          transition-duration: 400ms;
        }
      `}</style>

      <section className="h-screen w-full overflow-hidden relative bg-black">
        {/* Background video with overlay */}
        <video
          autoPlay
          muted
          loop
          className="absolute inset-0 w-full h-full object-cover"
        >
          <source
            src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260703_053131_1ec3dd1c-d627-44fb-ab20-6e1fce41b0d5.mp4"
            type="video/mp4"
          />
        </video>

        {/* Overlay */}
        <div className="absolute inset-0 bg-black/10"></div>

        {/* Content */}
        <div className="relative z-10 flex flex-col h-full">
          {/* Navigation */}
          <nav className="w-full px-5 sm:px-6 md:px-12 lg:px-16 py-4 sm:py-5">
            <div className="flex items-center justify-between">
              {/* Logo */}
              <a href="#" className="flex items-center">
                <DiamondLogo />
                <span className="text-white text-lg sm:text-xl font-medium tracking-tight">
                  flowpath
                </span>
              </a>

              {/* Desktop Navigation */}
              <div className="hidden md:flex items-center gap-8">
                {Object.entries(navConfig).map(([key, config]) => (
                  <NavItem key={key} label={config.label} dropdown={config} />
                ))}
                <NavItem label="Plans" />
              </div>

              {/* Desktop CTA */}
              <div className="hidden md:flex items-center gap-4">
                <a href="#" className="text-white/90 hover:text-white text-sm font-medium transition-colors">
                  Log in
                </a>
                <button className="liquid-glass rounded-full px-5 py-2 text-white text-sm font-medium hover:bg-white/10 transition-colors">
                  Try it free
                </button>
              </div>

              {/* Mobile Menu Button */}
              <button
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="md:hidden text-white transition-all duration-300"
              >
                {isMobileMenuOpen ? (
                  <X size={24} />
                ) : (
                  <Menu size={24} />
                )}
              </button>
            </div>

            {/* Mobile Menu */}
            <MobileMenu
              isOpen={isMobileMenuOpen}
              navConfig={navConfig}
              onClose={() => setIsMobileMenuOpen(false)}
            />
          </nav>

          {/* Hero Content */}
          <div className="flex-1 flex items-start justify-center pt-16 sm:pt-20 md:pt-24">
            <div className="text-center max-w-3xl px-5 sm:px-6">
              {/* Heading */}
              <h1 className="text-white text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl leading-[1.05] tracking-[-0.02em]">
                Bridge the
                <br />
                gaps.{' '}
                <span className="text-white/60">
                  Ditch the
                  <br />
                  grindwork.
                </span>
              </h1>

              {/* Subheading */}
              <p className="text-white/80 text-sm sm:text-base md:text-lg leading-relaxed max-w-md mx-auto mt-6 sm:mt-8">
                Flowpath unifies your complete wellness tools, so your crew spends less energy plugging gaps and more on real progress.
              </p>

              {/* CTA Buttons */}
              <div className="flex flex-wrap items-center justify-center gap-3 sm:gap-4 mt-6 sm:mt-8">
                <button className="px-5 sm:px-6 py-2.5 sm:py-3 bg-white text-gray-900 text-sm font-semibold rounded-full hover:bg-white/90 transition-colors">
                  Begin your journey
                </button>
                <button className="px-5 sm:px-6 py-2.5 sm:py-3 liquid-glass rounded-full text-white text-sm font-semibold hover:bg-white/10 transition-colors">
                  See it live
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </>
  );
};

export default HeroSection;
