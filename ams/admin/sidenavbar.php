 <aside class="sidebar">
        <div class="sidebar-header">
            <i class="ph ph-cube" style="color: var(--primary); font-size: 1.5rem;"></i>
            <span class="logo-text">NexusAdmin</span>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item active">
                <div class="menu-link active">
                    <div class="link-content">
                        <i class="ph ph-squares-four menu-icon"></i>
                        <a href="admin_dashboard.php" class="link-text">Dashboard</a>
                    </div>
                </div>
            </li>

            <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                        <i class="ph ph-users menu-icon"></i>
                        <span class="link-text">Customers</span>
                    </div>
                    <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="customer_list.php" class="submenu-link">All Customers</a></li>
                    <li><a href="add_customers.php" class="submenu-link">Add New</a></li>
                    <li><a href="active_customer.php" class="submenu-link">Active Customers</a></li>
                    <li><a href="inactive_customer.php" class="submenu-link">Inactive Customers</a></li>
                    <li><a href="customers_details.php" class="submenu-link">Customers Purchase Details</a></li>
                    <li><a href="customer_view.php" class="submenu-link">Customers Details</a></li>
                    <li><a href="customer_financials.php" class="submenu-link">Due Customer List</a></li>

                </ul>
            </li>
            <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                        <i class="ph ph-storefront menu-icon"></i>
                        <span class="link-text">Vendors</span>
                    </div>
                    <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="vendor_list.php" class="submenu-link">All Vendors</a></li>
                    <li><a href="add_vendor.php" class="submenu-link">Add Vendor</a></li>
                </ul>
            </li>
            <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                        <i class="ph ph-package menu-icon"></i>
                        <span class="link-text">Inventory</span>
                    </div>
                    <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="inventory_list.php" class="submenu-link">All Products</a></li>
                    <li><a href="add_product.php" class="submenu-link">Add Product</a></li>
                    <li><a href="low_stock_list.php" class="submenu-link">Low Stocks</a></li>
                </ul>
            </li>

            <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                        <i class="ph ph-shopping-cart-simple menu-icon"></i>
                        <span class="link-text">Sales</span>
                    </div>
                     <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_sale.php" class="submenu-link">Add Sale</a></li>
                    <li><a href="sales_list.php" class="submenu-link">Sale List</a></li>
                    <li><a href="verify_invoice.php" class="submenu-link">Verify Invoice</a></li>
                </ul>
            </li>
            
             <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                       <i class="ph ph-briefcase menu-icon"></i>
                        <span class="link-text">Employee</span>
                    </div>
                     <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_employee.php" class="submenu-link">Add Employees</a></li>
                    <li><a href="employee_list.php" class="submenu-link">All Employees</a></li>
                    <li><a href="check_salary.php" class="submenu-link">Check Salary</a></li>
                     <li><a href="salary_manager.php" class="submenu-link">Salary Manager</a></li>
                     <li><a href="attendance.php" class="submenu-link">Attendance</a></li>
                     <li><a href="attendance_report.php" class="submenu-link">Attendance Reports</a></li>
                </ul>
            </li>

            <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                        <i class="ph ph-currency-dollar menu-icon"></i>
                        <span class="link-text">Financials</span>
                    </div>
                    <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="fin_overview.php" class="submenu-link">Overview</a></li>
                    <li><a href="add_sale.php" class="submenu-link">Add Sale</a></li>
                    <li><a href="add_logistics.php" class="submenu-link">Add Expense</a></li>
                    
                </ul>
            </li>

             <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                         <i class="ph ph-factory menu-icon"></i>
                        <span class="link-text">Manufacturing</span>
                    </div>
                   <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_production_cost.php" class="submenu-link">Add Accessory Cost</a></li>
                    <li><a href="accessory_purchase_list.php" class="submenu-link">Accessory Purchase List</a></li>
                    <li><a href="vendor_purchases.php" class="submenu-link">Vendor Purchases</a></li>
                    
                    <li><a href="add_logistics.php" class="submenu-link">Add Logistics</a></li>
                    
                </ul>
            </li>
             <li class="menu-item has-submenu">
                <div class="menu-link">
                    <div class="link-content">
                         <i class="ph ph-handshake menu-icon"></i>
                        <span class="link-text">Subcontract</span>
                    </div>
                   <i class="ph ph-caret-down arrow-icon"></i>
                </div>
                <ul class="submenu">
                  <li><a href="add_subcontractor.php" class="submenu-link">Add Subcontractor</a></li>
                  <li><a href="subcontractor_management.php" class="submenu-link">Subcontractor List</a></li>
                  <li><a href="wig_cost_calculator.php" class="submenu-link">Wig Cost Calculator</a></li>
                  <li><a href="mirage_reports.php" class="submenu-link">Reports</a></li>
                  <li><a href="mirage_due_report.php" class="submenu-link">Due Payments</a></li>
                  <li><a href="mirage_batch_return.php" class="submenu-link active">Batch Returns</a></li>
                  <li><a href="mirage_return_report.php" class="submenu-link active">Returns Report</a></li>
                 <li><a href="mirage_make_payment.php" class="submenu-link active">Add Payments</a></li>
                    
                    
                </ul>
            </li>
            
            <li class="menu-item">
                <a href="settings.php" class="menu-link">
                    <div class="link-content">
                        <i class="ph ph-gear menu-icon"></i>
                        <span class="link-text">Settings</span>
                    </div>
                </a>
            </li>
        </ul>
    </aside>