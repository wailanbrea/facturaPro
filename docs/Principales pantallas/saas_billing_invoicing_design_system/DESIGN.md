---
name: SaaS Billing & Invoicing Design System
colors:
  surface: '#faf8ff'
  surface-dim: '#d9d9e5'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f2fe'
  surface-container: '#ededf9'
  surface-container-high: '#e8e7f3'
  surface-container-highest: '#e2e1ed'
  on-surface: '#1a1b23'
  on-surface-variant: '#434655'
  inverse-surface: '#2e3039'
  inverse-on-surface: '#f0f0fb'
  outline: '#747686'
  outline-variant: '#c4c5d7'
  surface-tint: '#2151da'
  primary: '#0037b0'
  on-primary: '#ffffff'
  primary-container: '#1d4ed8'
  on-primary-container: '#cad3ff'
  inverse-primary: '#b7c4ff'
  secondary: '#505f76'
  on-secondary: '#ffffff'
  secondary-container: '#d0e1fb'
  on-secondary-container: '#54647a'
  tertiary: '#7f2500'
  on-tertiary: '#ffffff'
  tertiary-container: '#a73400'
  on-tertiary-container: '#ffc9b7'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dce1ff'
  primary-fixed-dim: '#b7c4ff'
  on-primary-fixed: '#001551'
  on-primary-fixed-variant: '#0039b5'
  secondary-fixed: '#d3e4fe'
  secondary-fixed-dim: '#b7c8e1'
  on-secondary-fixed: '#0b1c30'
  on-secondary-fixed-variant: '#38485d'
  tertiary-fixed: '#ffdbcf'
  tertiary-fixed-dim: '#ffb59c'
  on-tertiary-fixed: '#390c00'
  on-tertiary-fixed-variant: '#832700'
  background: '#faf8ff'
  on-background: '#1a1b23'
  surface-variant: '#e2e1ed'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Inter
    fontSize: 30px
    fontWeight: '700'
    lineHeight: 38px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  title-lg:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
  code-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base_unit: 8px
  sidebar_width: 280px
  container_max_width: 1440px
  gutter: 24px
  margin_mobile: 16px
  stack_sm: 8px
  stack_md: 16px
  stack_lg: 32px
---

## Brand & Style
The design system is engineered for financial clarity, operational efficiency, and institutional trust. It targets administrative professionals and business owners who require a high-density, low-friction environment for managing complex billing cycles. 

The aesthetic follows a **Corporate Modern** approach. It prioritizes functionality over flourish, utilizing significant whitespace to reduce cognitive load during data entry. The interface feels "utilitarian-premium"—it is robust enough for enterprise use while maintaining the clean, lightweight feel of a modern SaaS application. The emotional response is one of control and reliability; users should feel that their data is secure and their workflows are logical.

## Colors
This design system utilizes a high-contrast palette optimized for legibility and state-driven navigation.

- **Primary (Blue 700):** Used for primary actions, active navigation states, and key interactive elements. It communicates stability and professionalism.
- **Secondary (Slate 500):** Used for secondary text, icons, and non-primary actions. It provides a neutral balance to the primary blue.
- **Status Semantic Palette:** 
  - **Emerald (Success):** Reserved for "PAGADA" (Paid) and positive financial flows.
  - **Amber (Warning):** Used for "PARCIAL" (Partial) and "PENDIENTE" (Pending) states.
  - **Rose (Danger):** Dedicated to "VENCIDA" (Overdue) and "ANULADA" (Voided) statuses.
- **Neutral:** The background uses a subtle Slate-50 (#F8FAFC) to allow white surface cards to pop with clear definition.

## Typography
Inter is the foundational typeface for this design system, chosen for its exceptional legibility in data-heavy environments. 

- **Headlines:** Use Semi-Bold (600) and Bold (700) weights with slight negative letter-spacing to maintain a structured, authoritative feel.
- **Body:** Standardized at 14px for administrative density, ensuring that large tables and forms remain readable without excessive scrolling.
- **Data Display:** Numerical values, particularly currencies (USD, EUR, DOP), should use `body-md` with tabular lining figures where possible to ensure columns of numbers align perfectly.
- **Status Labels:** Use `label-md` with a medium weight (500) and uppercase styling for immediate recognition of invoice states.

## Layout & Spacing
The layout follows a **Fixed Sidebar** model for desktop to provide persistent access to core billing modules (Invoices, Clients, Reports, Settings).

- **Grid System:** An 8px linear scale governs all spacing. Gutters between cards and major sections are set to 24px (3 units).
- **Responsive Behavior:** 
  - **Desktop:** Sidebar is fixed at 280px. Main content area is fluid up to 1440px.
  - **Mobile:** Sidebar collapses into a hamburger menu or bottom navigation. Content margins shrink to 16px.
- **Form Layouts:** Invoices are broken into logical sections (Header, Client, Items, Footer) using vertical stacks of 32px to ensure clear visual separation between the "Who" and the "What" of the billing data.

## Elevation & Depth
Hierarchy is established through **Tonal Layering** and soft, ambient shadows.

- **Background:** The base application layer is Slate-50.
- **Surfaces:** All primary content resides on "Level 1" white cards. These cards use a subtle 1px border (#E2E8F0) and a very soft, diffused shadow (`0 4px 6px -1px rgb(0 0 0 / 0.05)`) to create separation from the background.
- **Interactive Depth:** On hover, cards may increase shadow density slightly to indicate interactivity.
- **Modals/Overlays:** Use a "Level 2" elevation with a more pronounced shadow and a 40% opacity slate backdrop to focus the user’s attention on critical actions (e.g., "Confirm Void").

## Shapes
The design system employs a **Soft** shape language. 

- **Components:** Buttons, input fields, and small cards use a 4px (0.25rem) corner radius. This maintains a professional, crisp appearance without the "playfulness" of highly rounded corners.
- **Status Badges:** Use a fully rounded "pill" shape to distinguish them from interactive buttons or input fields.
- **Data Visualization:** Bars in charts and KPI cards follow the 4px rounding rule for consistency across the dashboard.

## Components

### Status Badges
Badges are pill-shaped with a light background tint and high-contrast text for accessibility.
- **BORRADOR:** Gray (Slate-100/Slate-700)
- **EMITIDA:** Blue (Blue-100/Blue-700)
- **PAGADA:** Green (Emerald-100/Emerald-700)
- **PARCIAL:** Amber (Amber-100/Amber-700)
- **VENCIDA:** Red (Rose-100/Rose-700)
- **ANULADA:** Dark Gray (Slate-200/Slate-900)

### Data Tables
Tables are the heart of the system.
- **Header:** Sticky headers with light gray background and uppercase labels.
- **Rows:** 1px bottom border. Hover state triggers a light blue (#EFF6FF) background highlight.
- **Currency Columns:** Right-aligned for easier scanning.

### Form Sections (Invoices)
- **Inputs:** 1px Slate-200 border, turning Blue-600 on focus. Labels sit directly above inputs in Semi-Bold 13px.
- **Item Rows:** Dynamic rows for adding products/services. Includes "Drag-to-reorder" handles and "Trash" icons in the far-right column.
- **Totals Section:** Located at the bottom right of the invoice form, with "Grand Total" using `title-lg` and Primary Blue text.

### KPI Cards
Display key metrics (Total Revenue, Outstanding, Overdue).
- **Structure:** Large numerical value, secondary label, and a small trend indicator (percentage + arrow icon).
- **Visuals:** White surface, subtle border, no shadow unless interactive.

### Icons
Use **Lucide** (2px stroke width) for all navigation and action items. Icons should be sized at 20px for standard actions and 24px for sidebar navigation.