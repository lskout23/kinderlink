<?php
class Router {
    private array $routes = [];

    public function __construct() {
        $this->registerRoutes();
    }

    private function registerRoutes(): void {
        // Public routes
        $this->add('GET',  '/',        'AuthController',      'loginPage');
        $this->add('POST', '/',        'AuthController',      'login');
        $this->add('POST', '/logout',  'AuthController',      'logout');
        $this->add('GET',  '/api/auth/ping',  'AuthController',      'ping');
        $this->add('GET',  '/forgot-password', 'AuthController', 'forgotPasswordPage');
        $this->add('POST', '/forgot-password', 'AuthController', 'forgotPassword');
        $this->add('GET',  '/forgot-username', 'AuthController', 'forgotUsernamePage');
        $this->add('POST', '/forgot-username', 'AuthController', 'forgotUsername');

        // Admin: Dashboard
        $this->add('GET',  '/dashboard',                           'DashboardController', 'index');

        // Admin: Children
        $this->add('GET',  '/administration/setup-children',       'ChildrenController',  'index');
        $this->add('POST', '/api/children',                        'ChildrenController',  'apiList');
        $this->add('POST', '/api/children/save',                   'ChildrenController',  'apiSave');
        $this->add('POST', '/api/children/bulk-action',            'ChildrenController',  'apiBulkAction');
        $this->add('POST', '/api/children/delete',                 'ChildrenController',  'apiDelete');

        // Admin: Groups
        $this->add('GET',  '/administration/setup-groups',         'GroupsController',    'index');
        $this->add('POST', '/api/groups',                          'GroupsController',    'apiList');
        $this->add('POST', '/api/groups/save',                     'GroupsController',    'apiSave');
        $this->add('POST', '/api/groups/delete',                   'GroupsController',    'apiDelete');

        // Admin: Children per group
        $this->add('GET',  '/administration/children-per-group',   'GroupsController',    'childrenPerGroup');
        $this->add('POST', '/api/groups/assign-children',          'GroupsController',    'apiAssignChildren');

        // Admin: Activities
        $this->add('GET',  '/administration/setup-activities',     'ActivitiesController','index');
        $this->add('POST', '/api/activities',                      'ActivitiesController','apiList');
        $this->add('POST', '/api/activities/save',                 'ActivitiesController','apiSave');
        $this->add('POST', '/api/activities/delete',               'ActivitiesController','apiDelete');

        // Admin: Parameters
        $this->add('GET',  '/administration/setup-parameters',     'ParametersController','index');
        $this->add('POST', '/administration/setup-parameters',     'ParametersController','save');
        $this->add('POST', '/api/parameters/attendance-migration',  'ParametersController','apiMigrateAttendance');

        // Admin: Email Template
        $this->add('GET',  '/administration/email-template',       'EmailTemplateController','index');
        $this->add('POST', '/administration/email-template',       'EmailTemplateController','save');

        // Admin: Personal Settings
        $this->add('GET',  '/administration/personal-details',     'PersonalController',  'index');
        $this->add('POST', '/administration/personal-details',     'PersonalController',  'save');
        $this->add('GET',  '/parent/personal-details',             'PersonalController',  'index');
        $this->add('POST', '/parent/personal-details',             'PersonalController',  'save');

        // Admin: Users
        $this->add('GET',  '/administration/setup-users',          'UsersController',     'index');
        $this->add('POST', '/api/users',                           'UsersController',     'apiList');
        $this->add('POST', '/api/users/save',                      'UsersController',     'apiSave');
        $this->add('POST', '/api/users/set-active',                'UsersController',     'apiSetActive');
        $this->add('POST', '/api/users/delete',                    'UsersController',     'apiDelete');
        $this->add('POST', '/api/users/groups',                    'UsersController',     'apiGroupsForUser');

        // Messages
        $this->add('GET',  '/messages/create-messages',            'MessagesController',  'create');
        $this->add('POST', '/api/messages/list-by-group',          'MessagesController',  'apiListByGroup');
        $this->add('POST', '/api/messages/attendance',             'MessagesController',  'apiAttendance');
        $this->add('POST', '/api/messages/save',                   'MessagesController',  'apiSave');
        $this->add('POST', '/api/messages/send-emails',            'MessagesController',  'apiSendEmails');
        $this->add('POST', '/api/messages/photos/list',            'MessagesController',  'apiPhotosList');
        $this->add('POST', '/api/messages/photos/upload',          'MessagesController',  'apiPhotosUpload');
        $this->add('POST', '/api/messages/photos/upload-group',    'MessagesController',  'apiPhotosUploadGroup');
        $this->add('POST', '/api/messages/photos/delete',          'MessagesController',  'apiPhotosDelete');
        $this->add('POST', '/api/messages/photos/delete-selected', 'MessagesController',  'apiPhotosDeleteSelected');
        $this->add('POST', '/api/messages/photos/unhide',          'MessagesController',  'apiPhotosUnhide');
        $this->add('POST', '/api/messages/photos/clear-day',       'MessagesController',  'apiPhotosClearDay');
        $this->add('POST', '/api/messages/photos/purge',           'MessagesController',  'apiPhotosPurge');
        $this->add('POST', '/api/messages/photos/purge-selected',  'MessagesController',  'apiPhotosPurgeSelected');
        $this->add('POST', '/api/messages/photos/purge-day',       'MessagesController',  'apiPhotosPurgeDay');
        $this->add('GET',  '/messages/photo',                      'MessagesController',  'photo');
        $this->add('GET',  '/messages/photo-view',                 'MessagesController',  'photoView');
        $this->add('GET',  '/messages/photo-zip',                  'MessagesController',  'photoZip');
        $this->add('POST', '/api/messages/photos/purge-all',       'MessagesController',  'apiPurgeAllPhotos');
        $this->add('POST', '/api/messages/email-preview',           'MessagesController',  'apiEmailPreview');
        // PWA
        $this->add('GET',  '/manifest.json',                       'PwaController',       'manifest');
        $this->add('GET',  '/sw.js',                               'PwaController',       'serviceWorker');        $this->add('GET',  '/messages/message-list',               'MessagesController',  'list');
        $this->add('POST', '/api/messages/search',                 'MessagesController',  'apiSearch');
        $this->add('POST', '/api/messages/delete',                 'MessagesController',  'apiDelete');
        $this->add('POST', '/api/messages/deleteBulk',             'MessagesController',  'apiDeleteBulk');
        $this->add('GET',  '/messages/free-email',                 'MessagesController',  'freeEmail');
        $this->add('POST', '/api/messages/send-free-email',        'MessagesController',  'apiSendFreeEmail');

        // Financial
        $this->add('GET',  '/financial/income',                    'FinancialController', 'income');
        $this->add('POST', '/api/financial/income-list',           'FinancialController', 'apiIncomeList');
        $this->add('POST', '/api/financial/income-save',           'FinancialController', 'apiIncomeSave');
        $this->add('POST', '/api/financial/income-delete',         'FinancialController', 'apiIncomeDelete');
        $this->add('GET',  '/financial/income-totals',             'FinancialController', 'incomeTotals');
        $this->add('POST', '/api/financial/income-totals-list',    'FinancialController', 'apiIncomeTotalsList');
        $this->add('GET',  '/financial/setup-activities',          'FinancialController', 'setupActivities');
        $this->add('POST', '/api/financial/activities',            'FinancialController', 'apiActivitiesList');
        $this->add('POST', '/api/financial/activities/save',       'FinancialController', 'apiActivitiesSave');
        $this->add('POST', '/api/financial/activities/delete',     'FinancialController', 'apiActivitiesDelete');
        $this->add('GET',  '/financial/expenses',                  'FinancialController', 'expenses');
        $this->add('POST', '/api/financial/expenses-list',         'FinancialController', 'apiExpensesList');
        $this->add('POST', '/api/financial/expenses-save',         'FinancialController', 'apiExpensesSave');
        $this->add('POST', '/api/financial/expenses-delete',       'FinancialController', 'apiExpensesDelete');

        // Parent portal
        $this->add('GET',  '/parent/dashboard',                    'ParentController',    'index');
        $this->add('POST', '/api/parent/messages',                 'ParentController',    'apiMessages');

        // Inbox (parent ↔ teacher private messages)
        $this->add('GET',  '/inbox',                               'InboxController',     'index');
        $this->add('POST', '/api/inbox/thread-list',               'InboxController',     'apiThreadList');
        $this->add('POST', '/api/inbox/thread-messages',           'InboxController',     'apiThreadMessages');
        $this->add('POST', '/api/inbox/send',                      'InboxController',     'apiSendMessage');
        $this->add('POST', '/api/inbox/reply',                     'InboxController',     'apiReply');
        $this->add('POST', '/api/inbox/delete-message',            'InboxController',     'apiDeleteMessage');
        $this->add('POST', '/api/inbox/children',                  'InboxController',     'apiChildrenForInbox');
    }

    private function add(string $method, string $path, string $controller, string $action): void {
        $this->routes[] = compact('method', 'path', 'controller', 'action');
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        // Support method override via _method field (for forms)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Strip base path if running in a subfolder
        $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = $uri ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                $controllerName = $route['controller'];
                $action         = $route['action'];
                $controller     = new $controllerName();
                $controller->$action();
                return;
            }
        }

        // 404
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
    }
}
