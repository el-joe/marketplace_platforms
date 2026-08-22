/** The API only returns a platform string, not a device model, so this maps to a generic label. */
export function getDevicePlatformLabelKey(platform: string): string {
  switch (platform) {
    case "ios":
      return "devicePlatformIos";
    case "android":
      return "devicePlatformAndroid";
    case "web":
      return "devicePlatformWeb";
    default:
      return "devicePlatformUnknown";
  }
}
