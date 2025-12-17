<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title>Clientura EMS - Employee Management System</title>
    <meta name="title" content="Clientura EMS - Employee Management System | Workforce Management Solution">
    <meta name="description" content="Clientura EMS is a comprehensive employee management system for streamlining workforce operations. Track attendance, manage tasks, monitor performance, and boost team productivity with our all-in-one platform.">
    <meta name="keywords" content="Clientura, employee management system, EMS, workforce management, attendance tracking, task management, employee portal, HR software, team management, productivity tools, performance tracking, leave management, employee dashboard">
    <meta name="author" content="Clientura">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="google-site-verification" content="V1MOgb3mW5SrkSBY3fQ1TnNnth000PoNZV6JzSP0KjU" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.clientura.org/">
    <meta property="og:title" content="Clientura EMS - Employee Management System">
    <meta property="og:description" content="Streamline your workforce management with Clientura EMS. Track attendance, assign tasks, manage teams, and boost productivity effortlessly.">
    <meta property="og:image" content="assets/img/clientura-logo.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://www.clientura.org/">
    <meta property="twitter:title" content="Clientura EMS - Employee Management System">
    <meta property="twitter:description" content="Streamline your workforce management with Clientura EMS. Track attendance, assign tasks, manage teams, and boost productivity effortlessly.">
    <meta property="twitter:image" content="assets/img/clientura-logo.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/clientura-logo.png">
    <link rel="apple-touch-icon" href="assets/img/clientura-logo.png">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://www.clientura.org/">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #c9a227;
            --primary-light: #e4c654;
            --primary-dark: #a68521;
            --dark: #1a1a2e;
            --dark-light: #25253d;
            --dark-lighter: #2d2d4a;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --success: #10b981;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--white);
            color: var(--gray-700);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 1rem 2rem;
            background: var(--dark);
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 0.75rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--white);
            transition: transform 0.3s ease;
        }
        
        .logo:hover {
            transform: scale(1.05);
        }
        
        .logo-icon {
            width: auto;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .logo-icon img {
            height: 45px;
            width: auto;
            object-fit: contain;
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--white);
            display: none;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.5rem;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--gray-300);
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        
        .nav-links a:hover {
            color: var(--white);
        }
        
        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .btn-outline-light {
            background: transparent;
            border: 1.5px solid var(--gray-500);
            color: var(--white);
        }
        
        .btn-outline-light:hover {
            border-color: var(--white);
            background: rgba(255,255,255,0.1);
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--dark);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }
        
        .btn-dark {
            background: var(--dark);
            color: var(--white);
            border: 1.5px solid var(--gray-600);
        }
        
        .btn-dark:hover {
            background: var(--dark-light);
            border-color: var(--gray-500);
        }
        
        /* Hero Section */
        .hero {
            background: var(--dark);
            padding: 8rem 2rem 4rem;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: linear-gradient(135deg, var(--dark-light) 0%, transparent 70%);
            z-index: 0;
        }
        
        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary);
            color: var(--dark);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .hero-content h1 {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 1.25rem;
            color: var(--white);
        }
        
        .hero-content h1 span {
            color: var(--primary);
        }
        
        .hero-content p {
            font-size: 1rem;
            color: var(--gray-400);
            margin-bottom: 2rem;
            max-width: 480px;
            line-height: 1.7;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 3rem;
        }
        
        .hero-buttons .btn {
            padding: 0.85rem 1.5rem;
        }
        
        /* Stats Row */
        .hero-stats {
            display: flex;
            gap: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--dark-lighter);
        }
        
        .stat-item h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 0.25rem;
        }
        
        .stat-item p {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        /* Dashboard Mockup */
        .hero-mockup {
            position: relative;
        }
        
        .dashboard-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        
        .dashboard-dots {
            display: flex;
            gap: 6px;
        }
        
        .dashboard-dots span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .dashboard-dots span:nth-child(1) { background: #ef4444; }
        .dashboard-dots span:nth-child(2) { background: #f59e0b; }
        .dashboard-dots span:nth-child(3) { background: #22c55e; }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        
        .dash-stat {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .dash-stat i {
            font-size: 1.25rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .dash-stat h4 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .dash-stat p {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .dashboard-chart {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 80px;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 10px;
        }
        
        .chart-bar {
            flex: 1;
            background: var(--primary);
            border-radius: 4px 4px 0 0;
            opacity: 0.7;
        }
        
        .chart-bar:nth-child(1) { height: 60%; }
        .chart-bar:nth-child(2) { height: 80%; }
        .chart-bar:nth-child(3) { height: 45%; }
        .chart-bar:nth-child(4) { height: 90%; opacity: 1; }
        .chart-bar:nth-child(5) { height: 70%; }
        .chart-bar:nth-child(6) { height: 55%; }
        .chart-bar:nth-child(7) { height: 85%; }
        
        /* Floating Card */
        .floating-card {
            position: absolute;
            background: var(--white);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .floating-card.top-right {
            top: -10px;
            right: -20px;
        }
        
        .floating-card.bottom-left {
            bottom: 20px;
            left: -30px;
        }
        
        .floating-icon {
            width: 40px;
            height: 40px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
        }
        
        .floating-card h5 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .floating-card p {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* Features Section */
        .features {
            padding: 5rem 2rem;
            background: var(--white);
        }
        
        .section-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-dark);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        
        .section-badge i {
            font-size: 0.7rem;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 3.5rem;
        }
        
        .section-header h2 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }
        
        .section-header p {
            color: var(--gray-500);
            font-size: 1rem;
            max-width: 550px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        
        .feature-card {
            padding: 1.75rem;
            border-radius: 14px;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
            background: var(--white);
        }
        
        .feature-card:hover {
            border-color: var(--gray-200);
            box-shadow: 0 10px 40px rgba(0,0,0,0.06);
            transform: translateY(-3px);
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: var(--gray-100);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            font-size: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .feature-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .feature-card p {
            color: var(--gray-500);
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        /* Why Choose Us Section */
        .why-us {
            padding: 5rem 2rem;
            background: var(--gray-50);
        }
        
        .why-us-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }
        
        .why-us-content .section-badge {
            justify-content: flex-start;
        }
        
        .why-us-content .section-header {
            text-align: left;
            margin-bottom: 2rem;
        }
        
        .why-us-content .section-header p {
            margin: 0;
        }
        
        .benefit-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .benefit-item {
            display: flex;
            gap: 1rem;
        }
        
        .benefit-icon {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .benefit-item h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .benefit-item p {
            font-size: 0.85rem;
            color: var(--gray-500);
            line-height: 1.5;
        }
        
        .why-us-image {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 100%);
            border-radius: 20px;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            position: relative;
            overflow: hidden;
        }
        
        .why-us-image::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.1;
            top: -50px;
            right: -50px;
        }
        
        .why-us-image i {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }
        
        .why-us-image h3 {
            color: var(--white);
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        /* Modules Section */
        .modules {
            padding: 5rem 2rem;
            background: var(--white);
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        
        .module-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 2rem 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .module-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 30px rgba(201, 162, 39, 0.1);
            transform: translateY(-3px);
        }
        
        .module-icon {
            width: 60px;
            height: 60px;
            background: var(--gray-100);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.5rem;
            color: var(--primary-dark);
        }
        
        .module-card:hover .module-icon {
            background: var(--primary);
            color: var(--dark);
        }
        
        .module-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .module-card p {
            font-size: 0.8rem;
            color: var(--gray-500);
            line-height: 1.5;
        }
        
        /* CTA Section */
        .cta {
            padding: 5rem 2rem;
            background: var(--dark);
            text-align: center;
        }
        
        .cta h2 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 0.75rem;
        }
        
        .cta p {
            font-size: 1rem;
            color: var(--gray-400);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .cta-buttons .btn {
            padding: 0.9rem 1.75rem;
        }
        
        /* Footer */
        footer {
            background: var(--gray-800);
            color: var(--white);
            padding: 4rem 2rem 2rem;
        }
        
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr repeat(3, 1fr);
            gap: 3rem;
            padding-bottom: 3rem;
            border-bottom: 1px solid var(--gray-700);
            margin-bottom: 2rem;
        }
        
        .footer-brand .logo {
            margin-bottom: 1rem;
        }
        
        .footer-brand p {
            color: var(--gray-400);
            font-size: 0.875rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        
        .social-links {
            display: flex;
            gap: 0.75rem;
        }
        
        .social-links a {
            width: 36px;
            height: 36px;
            background: var(--gray-700);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-300);
            transition: all 0.2s;
        }
        
        .social-links a:hover {
            background: var(--primary);
            color: var(--dark);
        }
        
        .footer-column h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--white);
            margin-bottom: 1.25rem;
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column ul li {
            margin-bottom: 0.75rem;
        }
        
        .footer-column ul a {
            color: var(--gray-400);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.2s;
        }
        
        .footer-column ul a:hover {
            color: var(--primary);
        }
        
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--gray-500);
            font-size: 0.8rem;
        }
        
        .footer-bottom span {
            color: var(--primary);
        }
        
        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: 2px solid var(--white);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-size: 1.4rem;
            color: var(--white);
            cursor: pointer;
            transition: all 0.3s ease;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }
        
        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            color: var(--primary);
            transform: scale(1.05);
        }
        
        .mobile-menu-btn.active {
            background: rgba(201, 162, 39, 0.2);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .navbar-container.mobile-menu-open .nav-links,
        .navbar-container.mobile-menu-open .nav-buttons {
            display: flex !important;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-content p {
                margin-left: auto;
                margin-right: auto;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .hero-mockup {
                max-width: 500px;
                margin: 0 auto;
                transform: scale(0.95);
            }
            
            .floating-card {
                display: none;
            }
            
            .features-grid,
            .modules-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .why-us-grid {
                grid-template-columns: 1fr;
            }
            
            .why-us-image {
                order: -1;
                min-height: 300px;
            }
            
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1.5rem;
            }
            
            .navbar-container {
                width: 100%;
                position: relative;
                display: grid;
                grid-template-columns: 1fr auto;
                grid-template-rows: auto;
                align-items: center;
                min-height: 44px;
            }
            
            .logo {
                z-index: 1001;
                position: relative;
                grid-column: 1;
                grid-row: 1;
            }
            
            .mobile-menu-btn {
                display: flex;
                position: relative;
                right: 0;
                top: 0;
                transform: none;
                grid-column: 2;
                grid-row: 1;
                margin-left: auto;
            }
            
            .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                margin-top: 0;
                gap: 0;
                padding: 1.5rem 0 0.75rem;
                border-top: 1px solid var(--dark-lighter);
                animation: slideDown 0.3s ease forwards;
                background: var(--dark);
                grid-column: 1 / -1;
                grid-row: 2;
            }
            
            .nav-links a {
                padding: 0.9rem 0;
                border-bottom: 1px solid var(--dark-lighter);
                font-size: 1rem;
                font-weight: 500;
                color: var(--gray-300);
                transition: all 0.2s ease;
            }
            
            .nav-links a:hover {
                padding-left: 0.5rem;
                color: var(--primary);
                background: rgba(201, 162, 39, 0.05);
            }
            
            .nav-links a:last-child {
                border-bottom: none;
            }
            
            .nav-buttons {
                display: none;
                flex-direction: column;
                width: 100%;
                gap: 0.75rem;
                padding: 1rem 0 1rem;
                border-top: 1px solid var(--dark-lighter);
                margin-top: 0;
                animation: slideDown 0.3s ease forwards;
                background: var(--dark);
                grid-column: 1 / -1;
                grid-row: 3;
            }
            
            .nav-buttons .btn {
                width: 100%;
                justify-content: center;
                padding: 0.9rem 1.25rem;
                font-size: 0.95rem;
            }
            
            .hero {
                padding: 7rem 1.5rem 3rem;
            }
            
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-stats {
                flex-wrap: wrap;
                gap: 1.5rem;
                border: none;
                padding: 0;
                margin-top: 2rem;
                justify-content: space-around;
            }
            
            .stat-item {
                flex: 0 1 calc(50% - 0.75rem);
            }
            
            .stat-item h3 {
                font-size: 1.5rem;
            }
            
            .stat-item p {
                font-size: 0.75rem;
            }
            
            .hero-mockup {
                max-width: 100%;
                transform: scale(0.9);
                transform-origin: center top;
                margin-top: 1rem;
            }
            
            .dashboard-card {
                padding: 1.25rem;
                border-radius: 12px;
            }
            
            .dashboard-stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .dash-stat {
                padding: 0.75rem;
                border-radius: 8px;
            }
            
            .dash-stat i {
                font-size: 1rem;
                margin-bottom: 0.25rem;
            }
            
            .dash-stat h4 {
                font-size: 1.25rem;
            }
            
            .dash-stat p {
                font-size: 0.7rem;
            }
            
            .dashboard-chart {
                height: 60px;
                gap: 6px;
                padding: 0.75rem;
            }
            
            .chart-bar {
                border-radius: 3px 3px 0 0;
            }
            
            .features-grid,
            .modules-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .feature-card,
            .module-card {
                padding: 1.5rem 1rem;
            }
            
            .feature-icon,
            .module-icon {
                margin-bottom: 1rem;
            }
            
            .feature-card h3,
            .module-card h3 {
                font-size: 0.95rem;
            }
            
            .feature-card p,
            .module-card p {
                font-size: 0.8rem;
            }
            
            .section-header h2 {
                font-size: 1.75rem;
            }
            
            .section-header p {
                font-size: 0.9rem;
            }
            
            .cta h2 {
                font-size: 1.75rem;
            }
            
            .cta p {
                font-size: 0.95rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .cta-buttons .btn {
                width: 100%;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
            
            .footer-brand p {
                font-size: 0.8rem;
            }
            
            .social-links {
                justify-content: center;
            }
            
            .footer-column h4 {
                font-size: 0.85rem;
            }
            
            .footer-column ul a {
                font-size: 0.8rem;
            }
            
            .footer-bottom {
                flex-direction: column;
                gap: 0.75rem;
                text-align: center;
                font-size: 0.75rem;
            }
            
            /* Benefit list adjustments */
            .benefit-item {
                gap: 0.75rem;
            }
            
            .benefit-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .benefit-item h4 {
                font-size: 0.9rem;
            }
            
            .benefit-item p {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 640px) {
            .navbar {
                padding: 0.5rem 1rem;
            }
            
            .navbar-container {
                gap: 0.5rem;
            }
            
            .logo-icon {
                height: 40px;
            }
            
            .logo-icon img {
                height: 40px;
            }
            
            .logo-text {
                font-size: 1rem;
            }
            
            .hero {
                padding: 6.5rem 1rem 2rem;
            }
            
            .hero-content h1 {
                font-size: 1.75rem;
                margin-bottom: 1rem;
            }
            
            .hero-content p {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn {
                width: 100%;
                padding: 0.75rem 1rem;
                font-size: 0.8rem;
            }
            
            .hero-mockup {
                transform: scale(0.8);
                margin-top: 0.5rem;
            }
            
            .hero-stats {
                gap: 1rem;
                margin-top: 1.5rem;
            }
            
            .stat-item {
                flex: 0 1 calc(50% - 0.5rem);
            }
            
            .stat-item h3 {
                font-size: 1.25rem;
            }
            
            .section-header {
                margin-bottom: 2.5rem;
            }
            
            .section-header h2 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .features-grid,
            .modules-grid {
                gap: 0.75rem;
            }
            
            .feature-card,
            .module-card {
                padding: 1.25rem 0.875rem;
            }
            
            .features,
            .why-us,
            .modules,
            .cta {
                padding: 3rem 1rem;
            }
            
            footer {
                padding: 2rem 1rem 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .hero {
                padding: 6rem 0.75rem 2rem;
            }
            
            .hero-content h1 {
                font-size: 1.5rem;
                line-height: 1.2;
            }
            
            .hero-content p {
                font-size: 0.85rem;
                line-height: 1.5;
            }
            
            .hero-badge {
                font-size: 0.7rem;
                padding: 0.3rem 0.8rem;
            }
            
            .hero-buttons {
                gap: 0.5rem;
            }
            
            .hero-mockup {
                transform: scale(1);
                margin-top: 0;
                width: 100%;
            }
            
            .dashboard-card {
                padding: 1rem;
            }
            
            .dashboard-stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }
            
            .dash-stat {
                padding: 0.5rem;
            }
            
            .dash-stat i {
                font-size: 0.9rem;
                margin-bottom: 0.15rem;
            }
            
            .dash-stat h4 {
                font-size: 1rem;
            }
            
            .dash-stat p {
                font-size: 0.65rem;
            }
            
            .dashboard-chart {
                height: 50px;
                gap: 4px;
                padding: 0.5rem;
            }
            
            .hero-stats {
                gap: 0.75rem;
                margin-top: 1rem;
            }
            
            .stat-item h3 {
                font-size: 1.1rem;
            }
            
            .stat-item p {
                font-size: 0.65rem;
            }
            
            .section-header h2 {
                font-size: 1.25rem;
            }
            
            .section-header p {
                font-size: 0.8rem;
            }
            
            .feature-card h3,
            .module-card h3 {
                font-size: 0.85rem;
            }
            
            .feature-card p,
            .module-card p {
                font-size: 0.75rem;
            }
            
            .why-us-image {
                min-height: 200px;
            }
            
            .why-us-image i {
                font-size: 2.5rem;
            }
            
            .why-us-image h3 {
                font-size: 1rem;
            }
            
            .cta h2 {
                font-size: 1.25rem;
            }
            
            .cta p {
                font-size: 0.85rem;
            }
            
            .footer-grid {
                gap: 1.5rem;
            }
            
            .footer-brand p {
                font-size: 0.75rem;
            }
            
            .social-links {
                gap: 0.5rem;
            }
            
            .social-links a {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="navbar-container">
            <a href="#" class="logo">
                <div class="logo-icon">
                    <img src="assets/img/clientura-logo.png" alt="ClientURA EMS Dashboard">
                </div>
                <span class="logo-text">EMS Dashboard</span>
            </a>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#modules">Modules</a>
                <a href="#why-us">Why Us</a>
            </div>
            <div class="nav-buttons">
                <a href="login.php" class="btn btn-outline-light">Employee Portal</a>
                <a href="login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login
                </a>
            </div>
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="bi bi-award-fill"></i>
                    #1 Employee Management System
                </div>
                <h1>
                    Streamline Your <span>Workforce</span> Management
                </h1>
                <p>
                    Manage your entire workforce with our all-in-one EMS platform.
                    Track attendance, assign tasks, manage teams, and boost productivity effortlessly.
                </p>
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">
                        <i class="bi bi-rocket-takeoff"></i>
                        Get Started
                    </a>
                    <a href="#features" class="btn btn-outline-light">
                        <i class="bi bi-play-circle"></i>
                        Learn More
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <h3>500+</h3>
                        <p>Active Users</p>
                    </div>
                    <div class="stat-item">
                        <h3>50K+</h3>
                        <p>Tasks Completed</p>
                    </div>
                    <div class="stat-item">
                        <h3>99.9%</h3>
                        <p>Uptime</p>
                    </div>
                </div>
            </div>
            <div class="hero-mockup">
                <div class="floating-card top-right">
                    <div class="floating-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <div>
                        <h5>Attendance Logged</h5>
                        <p>Check-in recorded</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-header">
                        <div class="dashboard-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    <div class="dashboard-stats">
                        <div class="dash-stat">
                            <i class="bi bi-people-fill"></i>
                            <h4>156</h4>
                            <p>Employees</p>
                        </div>
                        <div class="dash-stat">
                            <i class="bi bi-calendar-check"></i>
                            <h4>142</h4>
                            <p>Present Today</p>
                        </div>
                        <div class="dash-stat">
                            <i class="bi bi-list-task"></i>
                            <h4>48</h4>
                            <p>Active Tasks</p>
                        </div>
                    </div>
                    <div class="dashboard-chart">
                        <div class="chart-bar"></div>
                        <div class="chart-bar"></div>
                        <div class="chart-bar"></div>
                        <div class="chart-bar"></div>
                        <div class="chart-bar"></div>
                        <div class="chart-bar"></div>
                        <div class="chart-bar"></div>
                    </div>
                </div>
                <div class="floating-card bottom-left">
                    <div class="floating-icon" style="background: var(--primary);">
                        <i class="bi bi-bell-fill" style="color: var(--dark);"></i>
                    </div>
                    <div>
                        <h5>Task Assigned</h5>
                        <p>New task notification</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="bi bi-star-fill"></i>
                    Powerful Features
                </div>
                <h2>Everything You Need to Manage<br>Your Workforce</h2>
                <p>Our comprehensive EMS comes packed with features designed to simplify your daily operations and boost productivity.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3>Attendance Tracking</h3>
                    <p>Track check-ins and check-outs with photo verification and GPS location in real-time.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-kanban"></i>
                    </div>
                    <h3>Task Management</h3>
                    <p>Assign tasks to teams, track progress, review submissions, and manage deadlines efficiently.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3>Team Management</h3>
                    <p>Organize employees into teams, assign team leads, and streamline departmental operations.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-bell"></i>
                    </div>
                    <h3>Smart Notifications</h3>
                    <p>Automated alerts for task assignments, attendance reminders, and important updates.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h3>Reports & Analytics</h3>
                    <p>Generate detailed reports on attendance, tasks, and employee performance with visual charts.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </div>
                    <h3>PDF Export</h3>
                    <p>Export reports and attendance records in PDF format for easy sharing and record-keeping.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="why-us" id="why-us">
        <div class="section-container">
            <div class="why-us-grid">
                <div class="why-us-content">
                    <div class="section-header">
                        <div class="section-badge">
                            <i class="bi bi-star-fill"></i>
                            Why Choose Us
                        </div>
                        <h2>Built for Modern Organizations</h2>
                        <p>We understand the unique needs of growing businesses. That's why EMS Dashboard is tailored specifically for your organization.</p>
                    </div>
                    <div class="benefit-list">
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                            <div>
                                <h4>Lightning Fast</h4>
                                <p>Process attendance and tasks in seconds, not minutes. Your team won't have to wait.</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div>
                                <h4>Secure & Reliable</h4>
                                <p>Your data is protected with industry-standard security measures and regular backups.</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="bi bi-phone"></i>
                            </div>
                            <div>
                                <h4>Works Everywhere</h4>
                                <p>Access your EMS from any device - desktop, tablet, or smartphone with responsive design.</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="bi bi-emoji-smile"></i>
                            </div>
                            <div>
                                <h4>Easy to Use</h4>
                                <p>No technical expertise required. Get started in minutes with our intuitive interface.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="why-us-image">
                    <i class="bi bi-building-gear"></i>
                    <h3>Workforce Meets Technology</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- Modules Section -->
    <section class="modules" id="modules">
        <div class="section-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="bi bi-box-fill"></i>
                    Complete Solution
                </div>
                <h2>All-in-One HR Modules</h2>
                <p>Every tool you need to manage your workforce efficiently, all in one place.</p>
            </div>
            <div class="modules-grid">
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <h3>Employees</h3>
                    <p>Manage employee profiles, roles, and departments</p>
                </div>
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <h3>Attendance</h3>
                    <p>Track daily attendance with photo and location</p>
                </div>
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-list-task"></i>
                    </div>
                    <h3>Tasks</h3>
                    <p>Assign, track, and review tasks efficiently</p>
                </div>
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h3>Teams</h3>
                    <p>Organize teams with leads and members</p>
                </div>
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                    <h3>Reports</h3>
                    <p>Comprehensive analytics and insights</p>
                </div>
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-globe"></i>
                    </div>
                    <h3>Domains</h3>
                    <p>Manage work domains and categories</p>
                </div>
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <h3>Roles</h3>
                    <p>Define access levels and permissions</p>
                </div>
                <div class="module-card">
                    <div class="module-icon">
                        <i class="bi bi-cloud-arrow-up"></i>
                    </div>
                    <h3>Backup</h3>
                    <p>Secure data backup and restore functionality</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="section-container">
            <h2>Ready to Transform Your HR?</h2>
            <p>Join organizations who trust EMS Dashboard to manage their workforce. Start your journey to smarter HR management today.</p>
            <div class="cta-buttons">
                <a href="login.php" class="btn btn-primary">
                    <i class="bi bi-rocket-takeoff"></i>
                    Login Now
                </a>
                <a href="login.php" class="btn btn-dark">
                    <i class="bi bi-person"></i>
                    Employee Portal
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="#" class="logo">
                        <div class="logo-icon">
                            <img src="assets/img/clientura-logo.png" alt="ClientURA EMS Dashboard">
                        </div>
                        <span class="logo-text">EMS Dashboard</span>
                    </a>
                    <p>The complete employee management system for modern organizations. Streamline your operations and grow your business.</p>
                    <div class="social-links">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-twitter-x"></i></a>
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#modules">Modules</a></li>
                        <li><a href="#why-us">Why Us</a></li>
                        <li><a href="login.php">Login</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Modules</h4>
                    <ul>
                        <li><a href="login.php">Employees</a></li>
                        <li><a href="login.php">Attendance</a></li>
                        <li><a href="login.php">Tasks</a></li>
                        <li><a href="login.php">Reports</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="login.php">Employee Portal</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <span>EMS Dashboard</span>. All rights reserved.</p>
                <p>Built by <span>Aswin</span> for HR professionals.</p>
            </div>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Mobile menu toggle function
        function toggleMobileMenu() {
            const navbarContainer = document.querySelector('.navbar-container');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            
            if (!navbarContainer || !mobileBtn) return;
            
            const icon = mobileBtn.querySelector('i');
            const body = document.body;
            
            if (!icon) return;
            
            // Toggle the open state
            const isOpen = navbarContainer.classList.toggle('mobile-menu-open');
            mobileBtn.classList.toggle('active');
            
            // Toggle icon between hamburger and X
            if (isOpen) {
                icon.classList.remove('bi-list');
                icon.classList.add('bi-x');
                body.style.overflow = 'hidden';
            } else {
                icon.classList.remove('bi-x');
                icon.classList.add('bi-list');
                body.style.overflow = '';
            }
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Close mobile menu after clicking if it's open
                    const navbarContainer = document.querySelector('.navbar-container');
                    if (navbarContainer && navbarContainer.classList.contains('mobile-menu-open')) {
                        toggleMobileMenu();
                    }
                }
            });
        });
        
        // Close menu when clicking on any link
        document.addEventListener('DOMContentLoaded', function() {
            const menuLinks = document.querySelectorAll('.nav-links a, .nav-buttons a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const navbarContainer = document.querySelector('.navbar-container');
                    if (navbarContainer.classList.contains('mobile-menu-open')) {
                        toggleMobileMenu();
                    }
                });
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const navbar = document.querySelector('.navbar-container');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            
            if (navbar && !navbar.contains(event.target) && navbar.classList.contains('mobile-menu-open')) {
                toggleMobileMenu();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const navbarContainer = document.querySelector('.navbar-container');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            const body = document.body;
            
            if (window.innerWidth > 768 && navbarContainer && navbarContainer.classList.contains('mobile-menu-open')) {
                navbarContainer.classList.remove('mobile-menu-open');
                if (mobileBtn) {
                    mobileBtn.classList.remove('active');
                    const icon = mobileBtn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('bi-x');
                        icon.classList.add('bi-list');
                    }
                }
                body.style.overflow = '';
            }
        });
    </script>
</body>
</html>
