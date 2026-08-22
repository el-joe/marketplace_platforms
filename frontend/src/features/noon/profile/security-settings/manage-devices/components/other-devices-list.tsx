import Card from "@/src/components/shared/Card";
import DeviceRow from "./device-row";
import RevokeDeviceButton from "./revoke-device-button";
import type { ActiveSession } from "../../helpers/types";

type Props = {
  devices: ActiveSession[];
};

export default function OtherDevicesList({ devices }: Props) {
  return (
    <Card className="divide-y divide-border overflow-hidden">
      {devices.map((device) => (
        <DeviceRow
          key={device.id}
          device={device}
          action={<RevokeDeviceButton deviceId={device.id} />}
        />
      ))}
    </Card>
  );
}
