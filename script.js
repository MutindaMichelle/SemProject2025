
  const reviews = [
    {
      id: 1,
      name: "Alice",
      text: "Amazing platform! Loved the community.",
      image:  'https://images.unsplash.com/photo-1605462863863-10d9e47e15ee?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
      rating: 4,
    },
    {
      id: 2,
      name: "Bob",
      text: "Highly recommend this to my friends.",
      image: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
      rating: 5,
    },
    {
      id: 3,
      name: "Charlie",
      text: "Great features and excellent support!",
      image: 'https://images.unsplash.com/photo-1463453091185-61582044d556?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
      rating: 4,
    },
  ];

  const splideList = document.getElementById("splide-list");

  reviews.forEach((review) => {
    const slide = document.createElement("li");
    slide.className = "splide__slide";
    slide.innerHTML = `
      <img class="review-img" src="${review.image}" alt="${review.name}" />
      <div class="content">
        <p class="text">"${review.text}"</p>
        <div class="info">
          <p class="user">${review.name}</p>
          <div class="rating">${"★".repeat(review.rating)}${"☆".repeat(5 - review.rating)}</div>
        </div>
      </div>
    `;
    splideList.appendChild(slide);
  });

  // Initialize Splide after adding slides
  new Splide("#testimonial-slider", {
    type      : "loop",
    perPage   : 1,
    autoplay  : true,
    speed     : 1000,
    rewind    : true,
    interval  : 3000,
    pauseOnHover: true,
  }).mount();

