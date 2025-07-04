var app = new Vue({
    el: '#news',
    data: {
        articles: [],
        pagination: {
            'page': 1,
        },
    },
    methods: {
        isCurrentPage(page) {
            return this.pagination.page === page;
        },
        changePage(page) {
            if (page > this.pagination.pageCount) {
                page = this.pagination.pageCount;
            }
            this.pagination.page = page;
            this.fetchPosts();
        },
        fetchPosts() {
            axios.get('/articles/index.json?promoted=true&page=' + this.pagination.page)
                .then(response => {
                    this.articles = response.data.articles;
                    this.pagination = response.data.pagination.Articles;
                })
                .catch(error => {
                    console.log(error);
                });
        }
    },
    computed: {
        article: function() {
            return this.articles[0];
        },
        pages: function() {
            let pages = [];
            let from = this.pagination.page - Math.floor(this.pagination.perPage / 2);

            if (from < 1) {
                from = 1;
            }
            let to = from + this.pagination.perPage - 1;
            if (to > this.pagination.pageCount) {
                to = this.pagination.pageCount;
            }

            while (from <= to) {
                pages.push(from);
                from++;
            }

            return pages;
        }

    },
    mounted: function() {
        this.fetchPosts();
    }
});