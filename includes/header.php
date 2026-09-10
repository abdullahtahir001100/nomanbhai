<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
require_login();

$currentUser = get_current_user_profile();

if (!isset($pageTitle)) {
    $pageTitle = 'Smart Mobile - Wholesale & Retail Portal';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-primary-rgb: 13, 110, 253;
            --sidebar-width: 260px;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
            overflow-x: hidden;
        }
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: #0f172a;
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        #sidebar .nav-link {
            color: #94a3b8;
            padding: 12px 18px;
            border-radius: 10px;
            margin: 3px 14px;
            font-weight: 500;
            font-size: 0.92rem;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
        }
        #sidebar .nav-link i {
            width: 24px;
            font-size: 1.05rem;
        }
        #sidebar .nav-link:hover {
            color: #fff;
            background: #1e293b;
            transform: translateX(3px);
        }
        #sidebar .nav-link.active {
            color: #fff;
            background: #2563eb;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }
        #main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px;
            min-height: 100vh;
        }
        .stat-card {
            border: none;
            border-radius: 14px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
        }
        .wholesale-badge {
            background-color: #dcfce7;
            color: #166534;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        .retail-price {
            text-decoration: line-through;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        .avatar-circle {
            width: 42px;
            height: 42px;
            background-color: #e2e8f0;
            color: #334155;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .pos-item-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
            background: #fff;
        }
        .pos-item-card:hover {
            transform: translateY(-3px);
            border-color: #2563eb;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.1);
        }
        .cart-container {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }
        .cart-items-list {
            flex-grow: 1;
            overflow-y: auto;
        }
        .cursor-pointer {
            cursor: pointer;
        }
        .blur-price {
            filter: blur(4px);
            user-select: none;
        }
        .product-card {
            border: none;
            border-radius: 14px;
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
        }
        @media (max-width: 991px) {
            #sidebar {
                width: 100%;
                min-height: auto;
                position: relative;
            }
            #main-content {
                margin-left: 0;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
