import React from 'react';
import ChatterWidget, { ChatterWidgetProps } from './ChatterWidget';

export default function DocumentChatter(props: ChatterWidgetProps) {
    return <ChatterWidget {...props} />;
}
