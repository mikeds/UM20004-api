<p>You have successfully accepted cash-in with the following details:</p>
<table style="border: 1px solid black;border-collapse: collapse; width:50%">
    <tr>
        <th colspan="2">Transaction Details</th>
    </tr>
    <tr>
    <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Total Amount    :</td>
    <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['tx_amount']) ? number_format($post['tx_amount'], 2, '.', '') : ""?></td>
    </tr>
    <tr>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Remaining Balance    :</td>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['balance']) ? number_format($post['balance'], 2, '.', '') : ""?></td>
    </tr>
    <tr>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Ref No.  :</td>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['sender_ref_id']) ? $post['sender_ref_id'] : ""?></td>
    </tr>
    <tr>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px; font-weight: bold;">Transaction Date and Time  :</td>
        <td style="border: 1px solid black;border-collapse: collapse;padding: 15px;"><?=isset($post['timestamp']) ? $post['timestamp'] : ""?></td>
    </tr>
</table>