import React, { JSXElementConstructor } from "react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/src/components/ui/dropdown-menu";
type TItem = { itemLabel: string; value: string };
type Props = {
  triggerButton: React.ReactElement<
    unknown,
    string | JSXElementConstructor<unknown>
  >;
  groupOFItems?: [
    {
      groupLabel: string;
      items: TItem[];
    },
  ];
  items?: TItem[];
  listTitle?: string;
  onSelect?: (item: TItem) => void;
};

const Dropdown = ({
  triggerButton,
  groupOFItems,
  items,
  listTitle,
  onSelect = () => {},
}: Props) => {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger render={triggerButton} />
      <DropdownMenuContent>
        {groupOFItems ? (
          <>
            {groupOFItems.map((group) => (
              <DropdownMenuGroup key={group.groupLabel}>
                <DropdownMenuLabel>{group.groupLabel}</DropdownMenuLabel>
                {group.items.map((item) => (
                  <DropdownMenuItem
                    key={item.value}
                    onClick={() => onSelect(item)}
                  >
                    {item.itemLabel}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuGroup>
            ))}
          </>
        ) : (
          <DropdownMenuGroup>
            {listTitle && <DropdownMenuLabel>{listTitle}</DropdownMenuLabel>}
            {items?.map((item) => (
              <DropdownMenuItem key={item.value} onClick={() => onSelect(item)}>
                {item.itemLabel}
              </DropdownMenuItem>
            ))}
          </DropdownMenuGroup>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
};

export default Dropdown;
