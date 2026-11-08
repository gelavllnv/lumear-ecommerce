<form class="ht-form" method="POST" action="{{ $receiptAction }}">
    @csrf

    <label>
        Waybill on the physical parcel

        <input
            name="tracking_code"
            required
            maxlength="255"
            autocomplete="off"
            placeholder="Scan or enter waybill"
        >
    </label>

    <label>
        Parcel condition

        <input
            name="condition"
            maxlength="120"
            placeholder="Example: Intact"
        >
    </label>

    <label>
        Receipt remarks

        <textarea
            name="remarks"
            rows="2"
            maxlength="500"
        ></textarea>
    </label>

    <label class="ht-check">
        <input
            type="checkbox"
            name="physical_receipt"
            value="1"
            required
        >

        I checked and physically received this parcel at this hub.
    </label>

    <button
        class="rm-button rm-button-primary"
        type="submit"
    >
        Confirm Physical Receipt
    </button>
</form>