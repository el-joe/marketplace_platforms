export const getImageURL = (url:string)=>{
   return url?.startsWith("https" as string)
      ? url
      : "/images/no-image-available-icon.jpg";

}