import * as angular from "angular";
import Module from 'app.module';
import notify from 'notify';

import './article';
import './styles.scss';

const CONTROLLER_NAME = 'ArticlesController';
const STATE_NAME = 'articles';

export class ArticlesController {
    static $inject = ['$scope', '$http', '$state'];
    public articles: any[];
    public paginator: autowp.IPaginator;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/31/name',
            pageId: 31
        });

        var self = this;

        this.$http({
            method: 'GET',
            url: '/api/article',
            params: {
                page: this.$state.params.page,
                limit: 10,
                fields: 'description,author'
            }
        }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
            self.articles = response.data.items;
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, ArticlesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/articles',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
