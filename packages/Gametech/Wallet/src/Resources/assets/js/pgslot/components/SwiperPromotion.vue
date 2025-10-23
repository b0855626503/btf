<template>

  <div class="swiper mypromotion" ref="mypro">
    <div class="swiper-wrapper">

      <div class="swiper-slide" v-for="(item, index) in items" :key="index">

        <img :src="item.filepic">

        <button :data-id="item.code" class="getpro" v-if="getpro">รับโปรโมชั่น</button>
        <button disabled style="opacity:0.4;" v-else>รับโปรโมชั่น</button>
      </div>
    </div>
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-pagination"></div>
  </div>
  <!--  <swiper-->
  <!--      class="mypromotion"-->
  <!--      :modules="modules"-->
  <!--      :slides-per-view="1"-->
  <!--      :space-between="30"-->
  <!--      :navigation="true"-->
  <!--      ref="mypro"-->
  <!--      @swiper="onSwiper"-->
  <!--      @slideChange="onSlideChange"-->
  <!--  >-->


  <!--    <swiper-slide v-for="(item, index) in items" :key="index">-->
  <!--      <img :src="item.filepic">-->
  <!--      <button :data-id="item.code" class="getpro" v-if="getpro">รับโปรโมชั่น</button>-->
  <!--      <button disabled style="opacity:0.4;" v-else>รับโปรโมชั่น</button>-->
  <!--    </swiper-slide>-->


  <!--  </swiper>-->
</template>


<script>

// import { register } from 'swiper/element/bundle';
// register Swiper custom elements

// import Swiper core and required modules
// import {Navigation} from 'swiper/modules';

// Import Swiper Vue.js components
// import { Swiper, SwiperSlide} from 'swiper/vue';
// import {SwiperSlide, Swiper} from 'swiper/vue';

// Import Swiper styles
export default {
  name: 'mypro',
  data: function () {
    return {
      slide: [],
      items: {},
      getpro: false,
    }
  },
  created() {
    this.create();
  },
  mounted() {

    this.loadData();
    // this.$nextTick(() => {
    // this.loadData();
    this.$nextTick(() => {
      this.create();
    })
    //   console.log('after load');
    //   console.log(this.slide);
    //   console.log(this.swiperSlide);
    //
    // })
  },
  methods: {
    create() {
      this.slide = new Swiper(".mypromotion", {
        slidesPerView: "auto",
        spaceBetween: 30,
        loop: false,
        pagination: {
          el: ".swiper-pagination",
          clickable: true,
        },
        navigation: {
          nextEl: ".swiper-button-next",
          prevEl: ".swiper-button-prev",
        },
      });

      // register();
    },
    async loadData() {

      const response = await axios.get(`${this.$root.baseUrl}/member/promotion/api`);

      console.log(response);
      this.items = response.data.data.promotions;
      this.getpro = response.data.data.getpro;

    },
  }
};
</script>