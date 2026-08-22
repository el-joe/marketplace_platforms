export type CategoryNode = {
  id: string;
  label: string;
  children?: CategoryNode[];
};

export const categoryTree: CategoryNode[] = [
  {
    id: "fashion",
    label: "Fashion",
    children: [
      {
        id: "men",
        label: "Men",
        children: [
          { id: "men-shoes", label: "Shoes" },
          { id: "men-clothing", label: "Clothing" },
          { id: "men-watches", label: "Watches" },
        ],
      },
      {
        id: "women",
        label: "Women",
        children: [
          { id: "women-shoes", label: "Shoes" },
          { id: "women-clothing", label: "Clothing" },
          { id: "women-bags", label: "Bags" },
        ],
      },
      { id: "kids", label: "Kids" },
    ],
  },
  {
    id: "electronics",
    label: "Electronics",
    children: [
      { id: "mobiles", label: "Mobiles" },
      { id: "laptops", label: "Laptops" },
      { id: "accessories", label: "Accessories" },
    ],
  },
  { id: "beauty", label: "Beauty" },
  { id: "home", label: "Home" },
];

export const fulfilledByOptions = ["Supermall", "Express"];

export const arrivedByOptions = ["Today"];

export const brandOptions = [
  "Nike",
  "Adidas",
  "Puma",
  "Calvin Klein",
  "Lacoste",
  "Tommy Hilfiger",
  "Reebok",
];

export const dealsOptions = ["Today's deals", "Mega deals", "Clearance"];

export const priceDropOptions = ["10% or more", "25% or more", "50% or more"];

export type ColourOption = {
  name: string;
  value: string;
};

export const colourOptions: ColourOption[] = [
  { name: "Black", value: "#000000" },
  { name: "White", value: "#ffffff" },
  { name: "Red", value: "#e12413" },
  { name: "Blue", value: "#2122b8" },
  { name: "Green", value: "#38ae04" },
  { name: "Yellow", value: "#feee00" },
  { name: "Grey", value: "#7e859b" },
  { name: "Brown", value: "#7b4b2a" },
];
