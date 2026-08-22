import Card from "@/src/components/shared/Card";
import DeviceRow from "./device-row";
import SignOutThisDeviceButton from "./sign-out-this-device-button";
import type { ActiveSession } from "../../helpers/types";

type Props = {
  device: ActiveSession;
};

export default function ThisDeviceCard({ device }: Props) {
  return (
    <Card className="overflow-hidden">
      <DeviceRow device={device} action={<SignOutThisDeviceButton />} />
    </Card>
  );
}
