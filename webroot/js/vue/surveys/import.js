new Vue({
    el: '#app',
    data: {
        message: 'Pronti ad iniziare!',
        interval: null,
        max: 100,
        adv: 0,
    },
    methods: {
        loadData: function() {
            let self = this;
            axios.get('/surveys/getAdvancement.json')
                .then(response => {
                    self.message = response.data.message;
                    self.max = response.data.max;
                    self.adv = response.data.adv;
                })
                .catch(error => {
                    console.log(error);
                });
        }
    },
    mounted() {
        this.loadData();

        this.interval = setInterval(function() {
            this.loadData();
        }.bind(this), 3000);
    },
    beforeDestroy: function() {
        clearInterval(this.interval);
    }
});