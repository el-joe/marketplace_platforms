export interface IListing {
  id: number;
  images: string[];
  title: string;
  price: number;
  shortDescription: string;
  status: "new" | "used";
  phone: string;
  location: string;
}
