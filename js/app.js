'use strict';

// Declare app level module which depends on filters, and services
angular.module('myApp', ['myApp.filters', 'myApp.services', 'myApp.directives', 'myApp.controllers', 'ui.bootstrap', 'ngSanitize']).
  config(['$compileProvider', function ($compileProvider) {
	  $compileProvider.urlSanitizationWhitelist(/^\s*(https?|ftp|mailto|sms):/);
  }]).
  config(['$routeProvider', function($routeProvider) {
    $routeProvider.when('/gigs', {
		templateUrl: 'partials/partial2.html', 
		controller: 'MyCtrl2', 
		reloadOnSearch: false,
		resolve: {'MembersData': function(Members) {
				return Members.promise; },
			'GigsData': function(Gigs) {
				return Gigs.promise;
			}}
	});
	$routeProvider.when('/gigs/:gig_id/:action', {
		templateUrl: 'partials/partial2.html', 
		controller: 'MyCtrl2', 
		resolve: {'MembersData': function(Members) {
				return Members.promise; },
			'GigsData': function(Gigs) {
				return Gigs.promise;
			}}
	});

    $routeProvider.when('/create_gig', {templateUrl: 'partials/partial2.html', controller: 'MyCtrl2'});
    $routeProvider.otherwise({redirectTo: '/gigs'});
  }]);